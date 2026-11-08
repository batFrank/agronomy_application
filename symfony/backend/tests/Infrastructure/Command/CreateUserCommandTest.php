<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Command;

use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Shared\Uuid\UuidGenerator;
use App\Domain\User\User;
use App\Domain\User\UserRepository;
use App\Domain\User\UserRole;
use App\Infrastructure\Command\CreateUserCommand;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CreateUserCommandTest extends TestCase
{
    private function getCommand(
        ?UserRepository $userRepository = null,
        ?UserPasswordHasherInterface $passwordHasher = null,
        ?UuidGenerator $uuidGenerator = null,
    ): CommandTester {
        $userRepository = $userRepository ?? $this->createStub(UserRepository::class);
        $passwordHasher = $passwordHasher ?? $this->createStub(UserPasswordHasherInterface::class);
        $uuidGenerator = $uuidGenerator ?? $this->createStub(UuidGenerator::class);

        return new CommandTester(
            new CreateUserCommand(
                $userRepository,
                $passwordHasher,
                $uuidGenerator,
            )
        );
    }

    /**
     * @throws InvalidUuidException
     */
    public function testCreateUserSuccessfully(): void
    {
        $generatedUuid = UuidMother::create();

        $uuidGenerator = $this->createMock(UuidGenerator::class);
        $uuidGenerator->expects($this->once())
            ->method('generate')
            ->willReturn($generatedUuid);

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->willReturn('hashed_password');

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects($this->once())
            ->method('put')
            ->with(
                $this->callback(function (User $user) use ($generatedUuid) {
                    return $user->getId()->equals($generatedUuid)
                        && 'test@example.com' === $user->getEmail()->value()
                        && 'hashed_password' === $user->getPasswordHash()
                        && [UserRole::User] === $user->getRoles();
                })
            );

        $command = $this->getCommand($userRepository, $passwordHasher, $uuidGenerator);
        $exitCode = $command->execute([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $display = $command->getDisplay();
        $this->assertStringContainsString('Usuario creado exitosamente', $display);
        $this->assertStringContainsString('test@example.com', $display);
        $this->assertStringContainsString($generatedUuid->value(), $display);
    }

    /**
     * @throws InvalidUuidException
     */
    public function testCreateAdminUser(): void
    {
        $generatedUuid = UuidMother::create();

        $uuidGenerator = $this->createMock(UuidGenerator::class);
        $uuidGenerator->expects($this->once())
            ->method('generate')
            ->willReturn($generatedUuid);

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->willReturn('hashed_password');

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects($this->once())
            ->method('put')
            ->with(
                $this->callback(function (User $user) {
                    return \in_array(UserRole::Admin, $user->getRoles(), true)
                        && \in_array(UserRole::User, $user->getRoles(), true);
                })
            );

        $command = $this->getCommand($userRepository, $passwordHasher, $uuidGenerator);
        $exitCode = $command->execute([
            'email' => 'admin@example.com',
            'password' => 'password123',
            '--admin' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Usuario creado exitosamente', $command->getDisplay());
    }

    /**
     * @throws InvalidUuidException
     */
    public function testCreateUserWithCustomId(): void
    {
        $customUuid = UuidMother::create()->value();

        $uuidGenerator = $this->createMock(UuidGenerator::class);
        $uuidGenerator->expects($this->never())
            ->method('generate');

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->willReturn('hashed_password');

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects($this->once())
            ->method('put')
            ->with(
                $this->callback(function (User $user) use ($customUuid) {
                    return $customUuid === $user->getId()->value();
                })
            );

        $command = $this->getCommand($userRepository, $passwordHasher, $uuidGenerator);
        $exitCode = $command->execute([
            'email' => 'test@example.com',
            'password' => 'password123',
            '--id' => $customUuid,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString($customUuid, $command->getDisplay());
    }

    public function testCreateUserWithInvalidEmail(): void
    {
        $command = $this->getCommand();
        $exitCode = $command->execute([
            'email' => 'invalid-email',
            'password' => 'password123',
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Email inválido', $command->getDisplay());
    }

    public function testCreateUserWithInvalidUuid(): void
    {
        $command = $this->getCommand();
        $exitCode = $command->execute([
            'email' => 'test@example.com',
            'password' => 'password123',
            '--id' => 'invalid-uuid',
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('UUID inválido', $command->getDisplay());
    }

    /**
     * @throws InvalidUuidException
     */
    public function testCreateUserWithRepositoryException(): void
    {
        $generatedUuid = UuidMother::create();

        $uuidGenerator = $this->createMock(UuidGenerator::class);
        $uuidGenerator->expects($this->once())
            ->method('generate')
            ->willReturn($generatedUuid);

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->willReturn('hashed_password');

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects($this->once())
            ->method('put')
            ->willThrowException(new \Exception('Database error'));

        $command = $this->getCommand($userRepository, $passwordHasher, $uuidGenerator);
        $exitCode = $command->execute([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Error al crear usuario', $command->getDisplay());
    }
}
