<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\CreateUser;
use App\Application\Security\User as SecurityUser;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\User\Exception\EmailAlreadyInUseException;
use App\Domain\User\Exception\InvalidPasswordException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\User;
use App\Domain\User\UserCriteria;
use App\Domain\User\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

readonly class CreateUserCommandHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidPasswordException
     * @throws EmailAlreadyInUseException
     */
    public function handle(CreateUser $command): User
    {
        if (mb_strlen($command->getPassword()) < InvalidPasswordException::MIN_LENGTH) {
            throw InvalidPasswordException::tooShort(InvalidPasswordException::MIN_LENGTH);
        }

        $this->assertEmailIsFree($command);

        $tempUser = new SecurityUser(
            id: $command->getId(),
            email: $command->getEmail(),
            roles: $command->getRoles(),
        );

        $passwordHash = $this->passwordHasher->hashPassword($tempUser, $command->getPassword());

        $user = new User(
            id: $command->getId(),
            email: $command->getEmail(),
            roles: $command->getRoles(),
            passwordHash: $passwordHash,
            createdAt: new \DateTimeImmutable(),
            createdBy: $command->getCreatedBy(),
        );

        $this->userRepository->put($user);

        return $user;
    }

    /**
     * Read-then-write, so not atomic on its own: a concurrent duplicate or a soft-deleted user's email could
     * slip past this check. DBALUserRepository::insert() catches the unique constraint violation and throws
     * this same exception, so both cases still end in 422, not 500.
     *
     * @throws InfrastructureException
     * @throws EmailAlreadyInUseException
     */
    private function assertEmailIsFree(CreateUser $command): void
    {
        try {
            $this->userRepository->findOneBy(UserCriteria::createEmpty()->filterByEmail($command->getEmail()));
        } catch (UserNotFoundException) {
            return;
        }

        throw EmailAlreadyInUseException::create();
    }
}
