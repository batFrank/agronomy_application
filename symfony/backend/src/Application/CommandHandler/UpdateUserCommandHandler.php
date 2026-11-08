<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\UpdateUser;
use App\Application\Security\User as SecurityUser;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\User\Exception\UserForbiddenException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\User;
use App\Domain\User\UserCriteria;
use App\Domain\User\UserRepository;
use App\Domain\User\UserRole;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

readonly class UpdateUserCommandHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * @throws InfrastructureException
     * @throws UserNotFoundException
     * @throws UserForbiddenException
     */
    public function handle(UpdateUser $command): User
    {
        if (!$command->getRequestingUser()->hasRole(UserRole::Admin)
            && !$command->getId()->equals($command->getRequestingUser()->getId())
        ) {
            throw UserForbiddenException::cannotUpdateUser();
        }

        $existingUser = $this->userRepository->findOneBy(
            UserCriteria::createById($command->getId())
        );

        $email = $command->getEmail() ?? $existingUser->getEmail();
        $roles = $command->getRoles() ?? $existingUser->getRoles();

        $passwordHash = $existingUser->getPasswordHash();
        if (null !== $command->getPassword()) {
            $tempUser = new SecurityUser(
                id: $command->getId(),
                email: $email,
                roles: $roles,
            );
            $passwordHash = $this->passwordHasher->hashPassword($tempUser, $command->getPassword());
        }

        $user = new User(
            id: $existingUser->getId(),
            email: $email,
            roles: $roles,
            passwordHash: $passwordHash,
            createdAt: $existingUser->getCreatedAt(),
            updatedAt: new \DateTimeImmutable(),
            deletedAt: $existingUser->getDeletedAt(),
        );

        $this->userRepository->put($user);

        return $user;
    }
}
