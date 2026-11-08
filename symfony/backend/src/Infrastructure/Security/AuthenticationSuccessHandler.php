<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\Security\TokenInterface;
use App\Domain\Shared\Exception\InfrastructureException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface as SecurityTokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

readonly class AuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private TokenInterface $tokenService,
    ) {
    }

    /**
     * @throws InfrastructureException
     */
    public function onAuthenticationSuccess(Request $request, SecurityTokenInterface $token): Response
    {
        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            throw new \LogicException('User must be an instance of UserInterface');
        }

        $jwtToken = $this->tokenService->getJwtToken($user);
        $refreshToken = $this->tokenService->getRefreshToken($user);

        return new JsonResponse([
            'data' => [
                'token' => $jwtToken,
                'refresh_token' => $refreshToken,
            ],
        ], Response::HTTP_OK);
    }
}
