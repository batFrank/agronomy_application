<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler;

use App\Application\Command\UpdateUser;
use App\Application\CommandHandler\UpdateUserCommandHandler;
use App\Application\Security\User as SecurityUser;
use App\Domain\Security\SecurityException;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Shared\Uuid\Uuid;
use App\Domain\User\Exception\UserForbiddenException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\User;
use App\Domain\User\UserCriteria;
use App\Domain\User\UserRepository;
use App\Domain\User\UserRole;
use App\Tests\Mother\Domain\Shared\Email\EmailMother;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;
use App\Tests\Mother\Domain\User\UserMother;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AllowMockObjectsWithoutExpectations]
class UpdateUserCommandHandlerTest extends TestCase
{
    private UserRepository&MockObject $userRepository;
    private UserPasswordHasherInterface&MockObject $passwordHasher;
    private UpdateUserCommandHandler $handler;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->handler = new UpdateUserCommandHandler(
            $this->userRepository,
            $this->passwordHasher,
        );
    }

    /**
     * @param array<UserRole> $roles
     *
     * @throws SecurityException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     */
    private function createSecurityUser(
        ?Uuid $id = null,
        ?string $email = null,
        array $roles = [UserRole::User],
    ): SecurityUser {
        return new SecurityUser(
            id: $id ?? UuidMother::create(),
            email: EmailMother::create($email ?? 'security@example.com'),
            roles: $roles,
        );
    }

    /**
     * @throws InfrastructureException
     * @throws UserNotFoundException
     * @throws SecurityException
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    public function testAdminCanUpdateAnyUser(): void
    {
        $targetUserId = UuidMother::create();
        $adminUserId = UuidMother::create();

        $existingUser = UserMother::create(
            id: $targetUserId,
            email: EmailMother::create('old@example.com'),
            roles: [UserRole::User],
        );
        $adminSecurityUser = $this->createSecurityUser(
            id: $adminUserId,
            email: 'admin@example.com',
            roles: [UserRole::Admin, UserRole::User],
        );
        $newEmail = EmailMother::create('new@example.com');

        $command = new UpdateUser(
            id: $targetUserId,
            email: $newEmail,
            requestingUser: $adminSecurityUser,
        );

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with($this->callback(fn (UserCriteria $criteria) => true))
            ->willReturn($existingUser);

        $this->userRepository
            ->expects($this->once())
            ->method('put')
            ->with($this->callback(function (User $user) use ($targetUserId, $newEmail) {
                return $user->getId()->equals($targetUserId)
                    && $user->getEmail()->equals($newEmail)
                    && $user->getUpdatedAt() instanceof \DateTimeImmutable;
            }));

        $result = $this->handler->handle($command);

        $this->assertInstanceOf(User::class, $result);
        $this->assertTrue($result->getEmail()->equals($newEmail));
        $this->assertSame($existingUser->getRoles(), $result->getRoles());
        $this->assertSame($existingUser->getPasswordHash(), $result->getPasswordHash());
    }

    /**
     * @throws InfrastructureException
     * @throws UserNotFoundException
     * @throws SecurityException
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    public function testUserCanUpdateOwnData(): void
    {
        $userId = UuidMother::create();

        $existingUser = UserMother::create(
            id: $userId,
            email: EmailMother::create('user@example.com'),
            roles: [UserRole::User],
        );
        $securityUser = $this->createSecurityUser(
            id: $userId,
            email: 'user@example.com',
            roles: [UserRole::User],
        );
        $newEmail = EmailMother::create('newemail@example.com');

        $command = new UpdateUser(
            id: $userId,
            email: $newEmail,
            requestingUser: $securityUser,
        );

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn($existingUser);

        $this->userRepository
            ->expects($this->once())
            ->method('put');

        $result = $this->handler->handle($command);

        $this->assertTrue($result->getEmail()->equals($newEmail));
    }

    /**
     * Regression: the permission guard compares $command->getId() directly, which keeps whatever
     * case the request sent, against the requesting user's id, which is canonical. Both represent
     * the same user here, so self-editing must still be allowed.
     *
     * @throws InfrastructureException
     * @throws UserNotFoundException
     * @throws SecurityException
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    public function testUserCanUpdateOwnDataWithAnUppercaseUuidInTheCommand(): void
    {
        $canonicalUserId = UuidMother::create();
        $uppercaseUserId = new Uuid(mb_strtoupper($canonicalUserId->value()));

        $existingUser = UserMother::create(
            id: $canonicalUserId,
            email: EmailMother::create('user@example.com'),
            roles: [UserRole::User],
        );
        $securityUser = $this->createSecurityUser(
            id: $canonicalUserId,
            email: 'user@example.com',
            roles: [UserRole::User],
        );

        $command = new UpdateUser(
            id: $uppercaseUserId,
            email: EmailMother::create('newemail@example.com'),
            requestingUser: $securityUser,
        );

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn($existingUser);

        $this->userRepository
            ->expects($this->once())
            ->method('put');

        $this->handler->handle($command);
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    public function testNonAdminCannotUpdateOtherUser(): void
    {
        $this->expectException(UserForbiddenException::class);
        $this->expectExceptionMessage('You are not authorized to update this user');

        $targetUserId = UuidMother::create();
        $requestingUserId = UuidMother::create();

        $requestingSecurityUser = $this->createSecurityUser(
            id: $requestingUserId,
            email: 'requester@example.com',
            roles: [UserRole::User],
        );

        $command = new UpdateUser(
            id: $targetUserId,
            email: EmailMother::create('newemail@example.com'),
            requestingUser: $requestingSecurityUser,
        );

        $this->userRepository
            ->expects($this->never())
            ->method('findOneBy');

        $this->userRepository
            ->expects($this->never())
            ->method('put');

        $this->handler->handle($command);
    }

    /**
     * @throws InfrastructureException
     * @throws UserNotFoundException
     * @throws SecurityException
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    public function testHandleUpdatesRolesSuccessfully(): void
    {
        $id = UuidMother::create();
        $existingUser = UserMother::create(
            id: $id,
            roles: [UserRole::User],
        );
        $adminSecurityUser = $this->createSecurityUser(
            roles: [UserRole::Admin, UserRole::User],
        );
        $newRoles = [UserRole::Admin, UserRole::User];

        $command = new UpdateUser(
            id: $id,
            roles: $newRoles,
            requestingUser: $adminSecurityUser,
        );

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn($existingUser);

        $this->userRepository
            ->expects($this->once())
            ->method('put')
            ->with($this->callback(function (User $user) use ($newRoles) {
                return $user->getRoles() === $newRoles;
            }));

        $result = $this->handler->handle($command);

        $this->assertSame($newRoles, $result->getRoles());
        $this->assertTrue($result->getEmail()->equals($existingUser->getEmail()));
    }

    /**
     * @throws InfrastructureException
     * @throws UserNotFoundException
     * @throws SecurityException
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    public function testHandleUpdatesPasswordSuccessfully(): void
    {
        $userId = UuidMother::create();
        $existingUser = UserMother::create(
            id: $userId,
            passwordHash: 'old_hashed_password',
        );
        $securityUser = $this->createSecurityUser(
            id: $userId,
            roles: [UserRole::User],
        );
        $newPassword = 'newpassword123';
        $newHashedPassword = 'new_hashed_password';

        $command = new UpdateUser(
            id: $userId,
            password: $newPassword,
            requestingUser: $securityUser,
        );

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn($existingUser);

        $this->passwordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->willReturn($newHashedPassword);

        $this->userRepository
            ->expects($this->once())
            ->method('put')
            ->with($this->callback(function (User $user) use ($newHashedPassword) {
                return $user->getPasswordHash() === $newHashedPassword;
            }));

        $result = $this->handler->handle($command);

        $this->assertSame($newHashedPassword, $result->getPasswordHash());
    }

    /**
     * @throws InfrastructureException
     * @throws UserNotFoundException
     * @throws SecurityException
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    public function testHandleUpdatesAllFieldsSuccessfully(): void
    {
        $id = UuidMother::create();
        $existingUser = UserMother::create(
            id: $id,
            email: EmailMother::create('old@example.com'),
            roles: [UserRole::User],
            passwordHash: 'old_hashed_password',
        );
        $adminSecurityUser = $this->createSecurityUser(
            roles: [UserRole::Admin, UserRole::User],
        );
        $newEmail = EmailMother::create('new@example.com');
        $newRoles = [UserRole::Admin, UserRole::User];
        $newPassword = 'newpassword123';
        $newHashedPassword = 'new_hashed_password';

        $command = new UpdateUser(
            id: $id,
            email: $newEmail,
            password: $newPassword,
            roles: $newRoles,
            requestingUser: $adminSecurityUser,
        );

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn($existingUser);

        $this->passwordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->willReturn($newHashedPassword);

        $this->userRepository
            ->expects($this->once())
            ->method('put')
            ->with($this->callback(function (User $user) use ($id, $newEmail, $newRoles, $newHashedPassword) {
                return $user->getId()->equals($id)
                    && $user->getEmail()->equals($newEmail)
                    && $user->getRoles() === $newRoles
                    && $user->getPasswordHash() === $newHashedPassword
                    && $user->getUpdatedAt() instanceof \DateTimeImmutable;
            }));

        $result = $this->handler->handle($command);

        $this->assertTrue($result->getEmail()->equals($newEmail));
        $this->assertSame($newRoles, $result->getRoles());
        $this->assertSame($newHashedPassword, $result->getPasswordHash());
    }

    /**
     * @throws InfrastructureException
     * @throws SecurityException
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    public function testHandleThrowsExceptionWhenUserNotFound(): void
    {
        $this->expectException(UserNotFoundException::class);

        $adminSecurityUser = $this->createSecurityUser(
            roles: [UserRole::Admin, UserRole::User],
        );

        $command = new UpdateUser(
            id: UuidMother::create(),
            email: EmailMother::create(),
            requestingUser: $adminSecurityUser,
        );

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willThrowException(new UserNotFoundException());

        $this->handler->handle($command);
    }

    /**
     * @throws UserNotFoundException
     * @throws SecurityException
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    public function testHandleThrowsExceptionWhenRepositoryFails(): void
    {
        $this->expectException(InfrastructureException::class);

        $id = UuidMother::create();
        $existingUser = UserMother::create(id: $id);
        $adminSecurityUser = $this->createSecurityUser(
            roles: [UserRole::Admin, UserRole::User],
        );

        $command = new UpdateUser(
            id: $id,
            email: EmailMother::create('new@example.com'),
            requestingUser: $adminSecurityUser,
        );

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn($existingUser);

        $this->userRepository
            ->expects($this->once())
            ->method('put')
            ->willThrowException(InfrastructureException::fromPrevious(new \Exception('Database error')));

        $this->handler->handle($command);
    }

    /**
     * @throws InfrastructureException
     * @throws UserNotFoundException
     * @throws SecurityException
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    public function testHandlePreservesCreatedAtAndDeletedAt(): void
    {
        $id = UuidMother::create();
        $createdAt = new \DateTimeImmutable('2023-01-01 00:00:00');
        $deletedAt = new \DateTimeImmutable('2023-06-01 00:00:00');
        $existingUser = UserMother::create(
            id: $id,
            createdAt: $createdAt,
            deletedAt: $deletedAt,
        );
        $adminSecurityUser = $this->createSecurityUser(
            roles: [UserRole::Admin, UserRole::User],
        );

        $command = new UpdateUser(
            id: $id,
            email: EmailMother::create('new@example.com'),
            requestingUser: $adminSecurityUser,
        );

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn($existingUser);

        $this->userRepository
            ->expects($this->once())
            ->method('put')
            ->with($this->callback(function (User $user) use ($createdAt, $deletedAt) {
                return $user->getCreatedAt() === $createdAt
                    && $user->getDeletedAt() === $deletedAt;
            }));

        $result = $this->handler->handle($command);

        $this->assertSame($createdAt, $result->getCreatedAt());
        $this->assertSame($deletedAt, $result->getDeletedAt());
    }
}
