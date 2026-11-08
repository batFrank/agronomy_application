<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Validator\User;

use App\Infrastructure\Validator\User\PostUserPasswordResetValidator;
use App\Infrastructure\Validator\ValidatorException;
use PHPUnit\Framework\TestCase;

class PostUserPasswordResetValidatorTest extends TestCase
{
    /**
     * @throws ValidatorException
     */
    public function testValidPayloadPasses(): void
    {
        $payload = [
            'reset_token' => 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a1b2c3d4',
            'new_password' => 'newPassword123',
        ];

        $this->expectNotToPerformAssertions();
        PostUserPasswordResetValidator::validatePostUserPasswordResetRequest($payload);
    }

    public function testMissingTokenThrowsException(): void
    {
        $payload = [
            'new_password' => 'newPassword123',
        ];

        $this->expectException(ValidatorException::class);
        PostUserPasswordResetValidator::validatePostUserPasswordResetRequest($payload);
    }

    public function testMissingPasswordThrowsException(): void
    {
        $payload = [
            'reset_token' => 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a1b2c3d4',
        ];

        $this->expectException(ValidatorException::class);
        PostUserPasswordResetValidator::validatePostUserPasswordResetRequest($payload);
    }

    public function testEmptyTokenThrowsException(): void
    {
        $payload = [
            'reset_token' => '',
            'new_password' => 'newPassword123',
        ];

        $this->expectException(ValidatorException::class);
        PostUserPasswordResetValidator::validatePostUserPasswordResetRequest($payload);
    }

    public function testEmptyPasswordThrowsException(): void
    {
        $payload = [
            'reset_token' => 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a1b2c3d4',
            'new_password' => '',
        ];

        $this->expectException(ValidatorException::class);
        PostUserPasswordResetValidator::validatePostUserPasswordResetRequest($payload);
    }

    public function testTooShortTokenThrowsException(): void
    {
        $payload = [
            'reset_token' => 'short',
            'new_password' => 'newPassword123',
        ];

        $this->expectException(ValidatorException::class);
        PostUserPasswordResetValidator::validatePostUserPasswordResetRequest($payload);
    }

    public function testTooShortPasswordThrowsException(): void
    {
        $payload = [
            'reset_token' => 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a1b2c3d4',
            'new_password' => '123',
        ];

        $this->expectException(ValidatorException::class);
        PostUserPasswordResetValidator::validatePostUserPasswordResetRequest($payload);
    }

    public function testTooLongTokenThrowsException(): void
    {
        $payload = [
            'reset_token' => str_repeat('a', 129), // 129 characters
            'new_password' => 'newPassword123',
        ];

        $this->expectException(ValidatorException::class);
        PostUserPasswordResetValidator::validatePostUserPasswordResetRequest($payload);
    }

    public function testTooLongPasswordThrowsException(): void
    {
        $payload = [
            'reset_token' => 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a1b2c3d4',
            'new_password' => str_repeat('a', 256), // 256 characters
        ];

        $this->expectException(ValidatorException::class);
        PostUserPasswordResetValidator::validatePostUserPasswordResetRequest($payload);
    }

    public function testNonStringTokenThrowsException(): void
    {
        $payload = [
            'reset_token' => 123456,
            'new_password' => 'newPassword123',
        ];

        $this->expectException(ValidatorException::class);
        PostUserPasswordResetValidator::validatePostUserPasswordResetRequest($payload);
    }

    public function testNonStringPasswordThrowsException(): void
    {
        $payload = [
            'reset_token' => 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a1b2c3d4',
            'new_password' => 123456,
        ];

        $this->expectException(ValidatorException::class);
        PostUserPasswordResetValidator::validatePostUserPasswordResetRequest($payload);
    }
}
