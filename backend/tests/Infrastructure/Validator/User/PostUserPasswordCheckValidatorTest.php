<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Validator\User;

use App\Infrastructure\Validator\User\PostUserPasswordCheckValidator;
use App\Infrastructure\Validator\ValidatorException;
use PHPUnit\Framework\TestCase;

class PostUserPasswordCheckValidatorTest extends TestCase
{
    /**
     * @throws ValidatorException
     */
    public function testValidTokenPasses(): void
    {
        $payload = [
            'reset_token' => 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6',
        ];

        $this->expectNotToPerformAssertions();
        PostUserPasswordCheckValidator::validatePostUserPasswordCheckRequest($payload);
    }

    public function testMissingTokenThrowsException(): void
    {
        $payload = [];

        $this->expectException(ValidatorException::class);
        PostUserPasswordCheckValidator::validatePostUserPasswordCheckRequest($payload);
    }

    public function testEmptyTokenThrowsException(): void
    {
        $payload = [
            'reset_token' => '',
        ];

        $this->expectException(ValidatorException::class);
        PostUserPasswordCheckValidator::validatePostUserPasswordCheckRequest($payload);
    }

    public function testNullTokenThrowsException(): void
    {
        $payload = [
            'reset_token' => null,
        ];

        $this->expectException(ValidatorException::class);
        PostUserPasswordCheckValidator::validatePostUserPasswordCheckRequest($payload);
    }

    public function testTooShortTokenThrowsException(): void
    {
        $payload = [
            'reset_token' => 'short',
        ];

        $this->expectException(ValidatorException::class);
        PostUserPasswordCheckValidator::validatePostUserPasswordCheckRequest($payload);
    }

    public function testTooLongTokenThrowsException(): void
    {
        $payload = [
            'reset_token' => str_repeat('a', 129), // 129 characters
        ];

        $this->expectException(ValidatorException::class);
        PostUserPasswordCheckValidator::validatePostUserPasswordCheckRequest($payload);
    }

    public function testNonStringTokenThrowsException(): void
    {
        $payload = [
            'reset_token' => 123456,
        ];

        $this->expectException(ValidatorException::class);
        PostUserPasswordCheckValidator::validatePostUserPasswordCheckRequest($payload);
    }
}
