<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\ResetUserPassword;
use App\Application\Security\User as SecurityUser;
use App\Domain\Security\SecurityException;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\User;
use App\Domain\User\UserCriteria;
use App\Domain\User\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

readonly class ResetUserPasswordCommandHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * @throws InfrastructureException
     * @throws UserNotFoundException
     * @throws SecurityException
     */
    public function handle(ResetUserPassword $command): void
    {
        $user = $this->userRepository->findOneBy(
            UserCriteria::createEmpty()->filterByPasswordResetToken($command->getResetToken())
        );

        $now = new \DateTimeImmutable();
        if (null === $user->getPasswordResetTokenExpiration()
            || $user->getPasswordResetTokenExpiration() < $now) {
            throw new UserNotFoundException('Reset token has expired');
        }

        $userSecurity = SecurityUser::createFromDomainUser($user);
        $newPasswordHash = $this->passwordHasher->hashPassword($userSecurity, $command->getNewPassword());

        // Crear usuario actualizado con nueva contraseña y sin token de reset
        $updatedUser = new User(
            id: $user->getId(),
            email: $user->getEmail(),
            roles: $user->getRoles(),
            passwordHash: $newPasswordHash,
            createdAt: $user->getCreatedAt(),
            updatedAt: new \DateTimeImmutable(),
            deletedAt: $user->getDeletedAt(),
            passwordResetToken: null,
            passwordResetTokenExpiration: null,
        );

        $this->userRepository->put($updatedUser);
    }
}
