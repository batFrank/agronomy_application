<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler;

use App\Application\Command\ResetUserPassword;
use App\Application\CommandHandler\ResetUserPasswordCommandHandler;
use App\Domain\Security\SecurityException;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\User;
use App\Domain\User\UserRepository;
use App\Tests\Mother\Domain\User\UserMother;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ResetUserPasswordCommandHandlerTest extends TestCase
{
    private UserRepository&Stub $userRepository;
    private ResetUserPasswordCommandHandler $handler;
    private UserPasswordHasherInterface&Stub $passwordHasher;

    protected function setUp(): void
    {
        $this->userRepository = $this->createStub(UserRepository::class);
        $this->passwordHasher = $this->createStub(UserPasswordHasherInterface::class);

        $this->handler = new ResetUserPasswordCommandHandler(
            userRepository: $this->userRepository,
            passwordHasher: $this->passwordHasher,
        );
    }

    /**
     * @throws InfrastructureException
     * @throws UserNotFoundException
     * @throws SecurityException
     */
    public function testHandleResetsPasswordSuccessfully(): void
    {
        $token = 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a1b2c3d4';
        $oldPasswordHash = 'old_password_hash';
        $newPasswordHash = 'new_password_hash';

        $user = UserMother::create(
            passwordHash: $oldPasswordHash,
            passwordResetToken: $token,
            passwordResetTokenExpiration: new \DateTimeImmutable('+1 hour')
        );

        $this->userRepository->method('findOneBy')->willReturn($user);
        $this->passwordHasher->method('hashPassword')->willReturn($newPasswordHash);

        $savedUser = null;
        $this->userRepository->method('put')->willReturnCallback(function (User $u) use (&$savedUser): void {
            $savedUser = $u;
        });

        $this->handler->handle(new ResetUserPassword($token, 'newPassword123'));

        $this->assertNotNull($savedUser);
        $this->assertEquals($newPasswordHash, $savedUser->getPasswordHash());
        $this->assertNull($savedUser->getPasswordResetToken());
        $this->assertNull($savedUser->getPasswordResetTokenExpiration());
        $this->assertNotNull($savedUser->getUpdatedAt());
    }

    /**
     * @throws InfrastructureException
     * @throws UserNotFoundException
     * @throws SecurityException
     */
    public function testHandleThrowsExceptionForNonExistentToken(): void
    {
        $this->userRepository->method('findOneBy')->willThrowException(new UserNotFoundException());

        $this->expectException(UserNotFoundException::class);
        $this->handler->handle(new ResetUserPassword('nonexistent_token_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6', 'newPassword123'));
    }

    /**
     * @throws InfrastructureException
     * @throws UserNotFoundException
     * @throws SecurityException
     */
    public function testHandleThrowsExceptionForExpiredToken(): void
    {
        $token = 'b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a1b2c3d4e5f6';

        $user = UserMother::create(
            passwordResetToken: $token,
            passwordResetTokenExpiration: new \DateTimeImmutable('-1 hour')
        );
        $this->userRepository->method('findOneBy')->willReturn($user);

        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('Reset token has expired');
        $this->handler->handle(new ResetUserPassword($token, 'newPassword123'));
    }

    /**
     * @throws InfrastructureException
     * @throws UserNotFoundException
     * @throws SecurityException
     */
    public function testHandleThrowsExceptionForTokenWithoutExpiration(): void
    {
        $token = 'c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a1b2c3d4e5f6g7';

        $user = UserMother::create(
            passwordResetToken: $token,
            passwordResetTokenExpiration: null
        );
        $this->userRepository->method('findOneBy')->willReturn($user);

        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('Reset token has expired');
        $this->handler->handle(new ResetUserPassword($token, 'newPassword123'));
    }

    /**
     * @throws InfrastructureException
     * @throws UserNotFoundException
     * @throws SecurityException
     */
    public function testHandlePreservesOtherUserData(): void
    {
        $token = 'd4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a1b2c3d4e5f6g7h8';

        $user = UserMother::create(
            passwordResetToken: $token,
            passwordResetTokenExpiration: new \DateTimeImmutable('+1 hour')
        );

        $this->userRepository->method('findOneBy')->willReturn($user);

        $savedUser = null;
        $this->userRepository->method('put')->willReturnCallback(function (User $u) use (&$savedUser): void {
            $savedUser = $u;
        });

        $this->handler->handle(new ResetUserPassword($token, 'newPassword123'));

        $this->assertNotNull($savedUser);
        $this->assertEquals($user->getId(), $savedUser->getId());
        $this->assertEquals($user->getEmail(), $savedUser->getEmail());
        $this->assertEquals($user->getRoles(), $savedUser->getRoles());
        $this->assertEquals($user->getCreatedAt(), $savedUser->getCreatedAt());
        $this->assertEquals($user->getDeletedAt(), $savedUser->getDeletedAt());
    }
}
