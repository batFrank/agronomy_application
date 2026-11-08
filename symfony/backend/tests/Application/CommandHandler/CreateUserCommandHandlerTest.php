<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler;

use App\Application\Command\CreateUser;
use App\Application\CommandHandler\CreateUserCommandHandler;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\User\Exception\EmailAlreadyInUseException;
use App\Domain\User\Exception\InvalidPasswordException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\User;
use App\Domain\User\UserCriteria;
use App\Domain\User\UserRepository;
use App\Domain\User\UserRole;
use App\Tests\Mother\Domain\Shared\Email\EmailMother;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;
use App\Tests\Mother\Domain\User\UserMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CreateUserCommandHandlerTest extends TestCase
{
    private UserRepository&MockObject $userRepository;
    private UserPasswordHasherInterface&MockObject $passwordHasher;
    private CreateUserCommandHandler $handler;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->handler = new CreateUserCommandHandler(
            $this->userRepository,
            $this->passwordHasher,
        );
    }

    private function givenEmailIsFree(): void
    {
        $this->userRepository
            ->method('findOneBy')
            ->willThrowException(new UserNotFoundException());
    }

    /**
     * @throws InfrastructureException
     */
    #[DataProvider('tooShortPasswordProvider')]
    public function testHandleRejectsTooShortPassword(string $password): void
    {
        $this->expectException(InvalidPasswordException::class);
        $this->expectExceptionMessage('Password must be at least 6 characters.');

        $this->userRepository->expects($this->never())->method('findOneBy');
        $this->passwordHasher->expects($this->never())->method('hashPassword');
        $this->userRepository->expects($this->never())->method('put');

        $this->handler->handle(new CreateUser(
            id: UuidMother::create(),
            email: EmailMother::create(),
            password: $password,
            roles: [UserRole::User],
        ));
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function tooShortPasswordProvider(): \Generator
    {
        yield 'empty' => [''];
        yield 'five characters' => ['12345'];
        yield 'five multibyte characters' => ['ñññññ'];
    }

    /**
     * @throws InfrastructureException
     */
    public function testHandleAcceptsPasswordOfMinimumLength(): void
    {
        $this->givenEmailIsFree();
        $this->passwordHasher->expects($this->once())->method('hashPassword')->willReturn('hashed_password');
        $this->userRepository->expects($this->once())->method('put');

        $user = $this->handler->handle(new CreateUser(
            id: UuidMother::create(),
            email: EmailMother::create(),
            password: '123456',
            roles: [UserRole::User],
        ));

        $this->assertSame('hashed_password', $user->getPasswordHash());
    }

    /**
     * @throws InfrastructureException
     */
    public function testHandleRejectsEmailAlreadyInUse(): void
    {
        $this->expectException(EmailAlreadyInUseException::class);
        $this->expectExceptionMessage('Email is already in use.');

        $email = EmailMother::create('taken@example.com');

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with($this->callback(fn (UserCriteria $criteria) => $criteria->getEmail()?->equals($email) ?? false))
            ->willReturn(UserMother::create(email: $email));
        $this->passwordHasher->expects($this->never())->method('hashPassword');
        $this->userRepository->expects($this->never())->method('put');

        $this->handler->handle(new CreateUser(
            id: UuidMother::create(),
            email: $email,
            password: 'password123',
            roles: [UserRole::User],
        ));
    }

    /**
     * @throws InfrastructureException
     */
    public function testHandleThrowsExceptionWhenRepositoryFails(): void
    {
        $this->givenEmailIsFree();

        $this->expectException(InfrastructureException::class);

        $command = new CreateUser(
            id: UuidMother::create(),
            email: EmailMother::create(),
            password: 'password123',
            roles: [UserRole::User],
        );

        $this->passwordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->willReturn('hashed_password');

        $this->userRepository
            ->expects($this->once())
            ->method('put')
            ->willThrowException(InfrastructureException::fromPrevious(new \Exception('Database error')));

        $this->handler->handle($command);
    }

    /**
     * @throws InfrastructureException
     */
    public function testHandleCreatesUserSuccessfully(): void
    {
        $this->givenEmailIsFree();

        $id = UuidMother::create();
        $email = EmailMother::create('test@example.com');
        $password = 'password123';
        $roles = [UserRole::User];
        $hashedPassword = 'hashed_password_123';

        $command = new CreateUser(
            id: $id,
            email: $email,
            password: $password,
            roles: $roles,
        );

        $this->passwordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->willReturn($hashedPassword);

        $this->userRepository
            ->expects($this->once())
            ->method('put')
            ->with($this->callback(function (User $user) use ($id, $email, $roles, $hashedPassword) {
                return $user->getId()->equals($id)
                    && $user->getEmail()->equals($email)
                    && $user->getRoles() === $roles
                    && $user->getPasswordHash() === $hashedPassword;
            }));

        $result = $this->handler->handle($command);

        $this->assertInstanceOf(User::class, $result);
        $this->assertTrue($result->getId()->equals($id));
        $this->assertTrue($result->getEmail()->equals($email));
        $this->assertSame($roles, $result->getRoles());
        $this->assertSame($hashedPassword, $result->getPasswordHash());
    }

    /**
     * @throws InfrastructureException
     */
    public function testHandleCreatesAdminUserSuccessfully(): void
    {
        $this->givenEmailIsFree();

        $id = UuidMother::create();
        $email = EmailMother::create('admin@example.com');
        $password = 'adminpassword';
        $roles = [UserRole::Admin, UserRole::User];
        $hashedPassword = 'hashed_admin_password';

        $command = new CreateUser(
            id: $id,
            email: $email,
            password: $password,
            roles: $roles,
        );

        $this->passwordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->willReturn($hashedPassword);

        $this->userRepository
            ->expects($this->once())
            ->method('put');

        $result = $this->handler->handle($command);

        $this->assertContains(UserRole::Admin, $result->getRoles());
        $this->assertContains(UserRole::User, $result->getRoles());
    }

    /**
     * @throws InfrastructureException
     */
    public function testHandleCreatesUserWithCreatedBy(): void
    {
        $this->givenEmailIsFree();

        $id = UuidMother::create();
        $email = EmailMother::create('user@example.com');
        $password = 'password123';
        $roles = [UserRole::User];
        $hashedPassword = 'hashed_password_123';
        $createdBy = UuidMother::create();

        $command = new CreateUser(
            id: $id,
            email: $email,
            password: $password,
            roles: $roles,
            createdBy: $createdBy,
        );

        $this->passwordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->willReturn($hashedPassword);

        $this->userRepository
            ->expects($this->once())
            ->method('put')
            ->with($this->callback(function (User $user) use ($id, $email, $roles, $hashedPassword, $createdBy) {
                return $user->getId()->equals($id)
                    && $user->getEmail()->equals($email)
                    && $user->getRoles() === $roles
                    && $user->getPasswordHash() === $hashedPassword
                    && $user->getCreatedBy()?->equals($createdBy);
            }));

        $result = $this->handler->handle($command);

        $this->assertInstanceOf(User::class, $result);
        $this->assertTrue($result->getId()->equals($id));
        $this->assertTrue($result->getEmail()->equals($email));
        $this->assertSame($roles, $result->getRoles());
        $this->assertSame($hashedPassword, $result->getPasswordHash());
        $this->assertTrue($result->getCreatedBy()?->equals($createdBy));
    }
}
