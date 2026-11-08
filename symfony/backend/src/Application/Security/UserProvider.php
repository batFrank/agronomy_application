<?php

declare(strict_types=1);

namespace App\Application\Security;

use App\Domain\Shared\Email\Email;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\UserCriteria;
use App\Domain\User\UserRepository;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @implements UserProviderInterface<UserInterface>
 */
readonly class UserProvider implements UserProviderInterface
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    /**
     * @throws InfrastructureException
     */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        try {
            $user = $this->userRepository->findOneBy(
                UserCriteria::createEmpty()->filterByEmail(new Email($identifier))
            );

            return User::createFromDomainUser($user);
        } catch (UserNotFoundException|InvalidEmailException $e) {
            throw new \Symfony\Component\Security\Core\Exception\UserNotFoundException(
                message: $e->getMessage(),
                code: $e->getCode(),
                previous: $e,
            );
        }
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        throw new \LogicException('Not implemented');
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class;
    }
}
