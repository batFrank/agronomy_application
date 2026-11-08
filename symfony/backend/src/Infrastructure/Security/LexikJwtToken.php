<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Application\Security\User as SecurityUser;
use App\Domain\Security\Exceptions\InvalidTokenException;
use App\Domain\Security\Exceptions\RefreshTokenNotFoundException;
use App\Domain\Security\Exceptions\TokenInterfaceException;
use App\Domain\Security\RefreshToken;
use App\Domain\Security\RefreshTokenCriteria;
use App\Domain\Security\RefreshTokenRepository;
use App\Domain\Security\SecurityException;
use App\Domain\Security\TokenInterface;
use App\Domain\Shared\Email\Email;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\UserCriteria;
use App\Domain\User\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Random\RandomException;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;

readonly class LexikJwtToken implements TokenInterface
{
    public function __construct(
        private JWTTokenManagerInterface $jwtManager,
        private RefreshTokenRepository $refreshTokenRepository,
        private UserRepository $userRepository,
        private int $jwtTokenTtl,
    ) {
    }

    /**
     * @param array<string, mixed> $extra
     */
    #[\Override]
    public function getJwtToken(UserInterface $user, array $extra = []): string
    {
        $payload = [
            'email' => $user->getUserIdentifier(),
        ];

        if ($user instanceof SecurityUser) {
            $payload = array_merge($payload, [
                'id' => $user->getId()->value(),
                'roles' => $user->getRoles(),
            ]);
        }

        $payload = array_merge($payload, $extra);

        return $this->jwtManager->createFromPayload($user, $payload);
    }

    /**
     * @throws \DateMalformedStringException
     * @throws InfrastructureException
     * @throws RandomException
     */
    #[\Override]
    public function getRefreshToken(UserInterface $user): string
    {
        // Clean up expired tokens for this user before creating a new one
        $previousTokens = $this->refreshTokenRepository->findBy(
            RefreshTokenCriteria::createEmpty()->filterByUsername($user->getUserIdentifier())
        );
        foreach ($previousTokens as $token) {
            if (null === $token->getValid() || $token->getValid() < new \DateTimeImmutable()) {
                $this->refreshTokenRepository->delete($token);
            }
        }

        while (true) {
            $token = bin2hex(random_bytes(64));
            try {
                $this->refreshTokenRepository->findOneBy(
                    RefreshTokenCriteria::createEmpty()->filterByToken($token)
                );
            } catch (RefreshTokenNotFoundException) {
                break;
            }
        }

        $refreshToken = new RefreshToken(
            refreshToken: $token,
            username: $user->getUserIdentifier(),
            valid: new \DateTimeImmutable('+' . ($this->jwtTokenTtl * 2) . ' seconds'),
        );

        $this->refreshTokenRepository->put($refreshToken);

        return $token;
    }

    /**
     * @throws InfrastructureException
     * @throws UserNotFoundException
     * @throws InvalidEmailException
     * @throws SecurityException
     */
    #[\Override]
    public function getUser(string $token): UserInterface
    {
        try {
            $payload = $this->jwtManager->parse($token); // Lexik parser verify if the token is valid
        } catch (JWTDecodeFailureException $e) {
            throw TokenInterfaceException::fromPrevious($e);
        }

        $user = $this->userRepository->findOneBy(
            UserCriteria::createEmpty()
            ->filterByEmail(new Email($payload['email']))
        );

        return SecurityUser::createFromDomainUser($user);
    }

    #[\Override]
    public function validateRefreshToken(string $token): string
    {
        $refreshToken = $this->refreshTokenRepository->findOneBy(
            RefreshTokenCriteria::createEmpty()->filterByToken($token)
        );

        if (null === $refreshToken->getValid() || $refreshToken->getValid() < new \DateTimeImmutable()) {
            throw new InvalidTokenException();
        }

        $username = $refreshToken->getUsername();

        if (null === $username) {
            throw new InvalidTokenException();
        }

        // Refresh tokens must be of single use
        $this->refreshTokenRepository->delete($refreshToken);

        return $username;
    }

    /**
     * @throws InfrastructureException
     * @throws \DateMalformedStringException
     * @throws RandomException
     */
    public function setTokensCookie(Response $response, UserInterface $user): void
    {
        $response->headers->setCookie(
            Cookie::create(
                name: 'BEARER',
                value: $this->getJwtToken($user),
                expire: (new \DateTimeImmutable())->modify($this->jwtTokenTtl . ' seconds'),
                httpOnly: false
            )
        );

        $response->headers->setCookie(
            Cookie::create(
                name: 'refresh_token',
                value: $this->getRefreshToken($user),
                expire: (new \DateTimeImmutable())->modify(($this->jwtTokenTtl * 2) . ' seconds'),
            )
        );
    }
}
