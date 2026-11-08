<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler;

use App\Application\Command\CheckPasswordResetToken;
use App\Application\CommandHandler\CheckPasswordResetTokenCommandHandler;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\UserRepository;
use App\Tests\Mother\Domain\User\UserMother;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class CheckPasswordResetTokenCommandHandlerTest extends TestCase
{
    private UserRepository&Stub $userRepository;
    private CheckPasswordResetTokenCommandHandler $handler;

    protected function setUp(): void
    {
        $this->userRepository = $this->createStub(UserRepository::class);
        $this->handler = new CheckPasswordResetTokenCommandHandler($this->userRepository);
    }

    /**
     * @throws InfrastructureException
     */
    public function testHandleReturnsTrueForValidToken(): void
    {
        $token = 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a1b2c3d4';
        $user = UserMother::create(
            passwordResetToken: $token,
            passwordResetTokenExpiration: new \DateTimeImmutable('+1 hour')
        );
        $this->userRepository->method('findOneBy')->willReturn($user);

        $result = $this->handler->handle(new CheckPasswordResetToken($token));

        $this->assertTrue($result);
    }

    /**
     * @throws InfrastructureException
     */
    public function testHandleReturnsFalseForNonExistentToken(): void
    {
        $this->userRepository->method('findOneBy')->willThrowException(new UserNotFoundException());

        $result = $this->handler->handle(new CheckPasswordResetToken('z9y8x7w6v5u4t3s2r1q0p9o8n7m6l5k4j3i2h1g0f9e8d7c6b5a4z9y8x7w6'));

        $this->assertFalse($result);
    }

    /**
     * @throws InfrastructureException
     */
    public function testHandleReturnsFalseForExpiredToken(): void
    {
        $token = 'b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a1b2c3d4e5f6';
        $user = UserMother::create(
            passwordResetToken: $token,
            passwordResetTokenExpiration: new \DateTimeImmutable('-1 hour')
        );
        $this->userRepository->method('findOneBy')->willReturn($user);

        $result = $this->handler->handle(new CheckPasswordResetToken($token));

        $this->assertFalse($result);
    }

    /**
     * @throws InfrastructureException
     */
    public function testHandleReturnsFalseForTokenWithoutExpiration(): void
    {
        $token = 'c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a1b2c3d4e5f6g7';
        $user = UserMother::create(
            passwordResetToken: $token,
            passwordResetTokenExpiration: null
        );
        $this->userRepository->method('findOneBy')->willReturn($user);

        $result = $this->handler->handle(new CheckPasswordResetToken($token));

        $this->assertFalse($result);
    }

    /**
     * @throws InfrastructureException
     */
    public function testHandleReturnsFalseForUserWithoutToken(): void
    {
        $this->userRepository->method('findOneBy')->willThrowException(new UserNotFoundException());

        $result = $this->handler->handle(new CheckPasswordResetToken('d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a1b2c3d4e5f6g7h8'));

        $this->assertFalse($result);
    }
}
