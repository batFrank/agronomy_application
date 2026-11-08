<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\CheckPasswordResetToken;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\UserCriteria;
use App\Domain\User\UserRepository;

readonly class CheckPasswordResetTokenCommandHandler
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    /**
     * @throws InfrastructureException
     */
    public function handle(CheckPasswordResetToken $command): bool
    {
        try {
            $user = $this->userRepository->findOneBy(
                UserCriteria::createEmpty()->filterByPasswordResetToken($command->getResetToken())
            );

            // Verificar que el token no ha expirado
            $now = new \DateTimeImmutable();
            if (null === $user->getPasswordResetTokenExpiration()
                || $user->getPasswordResetTokenExpiration() < $now) {
                return false;
            }

            return true;
        } catch (UserNotFoundException) {
            return false;
        }
    }
}
