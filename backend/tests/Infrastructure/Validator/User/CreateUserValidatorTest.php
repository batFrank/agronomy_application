<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Validator\User;

use App\Infrastructure\Validator\User\CreateUserValidator;
use App\Infrastructure\Validator\ValidatorException;
use App\Tests\Infrastructure\Validator\ValidatorTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class CreateUserValidatorTest extends ValidatorTestCase
{
    /**
     * @param array<string, mixed> $data
     *
     * @throws ValidatorException
     */
    #[DataProvider('validRequestDataProvider')]
    public function testValidRequestDoesNotThrowException(array $data): void
    {
        CreateUserValidator::validateCreateUserRequest($data);

        $this->expectNotToPerformAssertions();
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function validRequestDataProvider(): array
    {
        return [
            'single role' => [
                [
                    'email' => 'test@example.com',
                    'password' => 'password123',
                    'password_confirmation' => 'password123',
                    'roles' => ['ROLE_USER'],
                ],
            ],
            'multiple roles' => [
                [
                    'email' => 'admin@example.com',
                    'password' => 'securepassword',
                    'password_confirmation' => 'securepassword',
                    'roles' => ['ROLE_USER', 'ROLE_ADMIN'],
                ],
            ],
            'admin only role' => [
                [
                    'email' => 'admin@example.com',
                    'password' => 'securepassword',
                    'password_confirmation' => 'securepassword',
                    'roles' => ['ROLE_ADMIN'],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed>                $data
     * @param array<string, string|array<string>> $expectedViolations
     */
    #[DataProvider('invalidRequestDataProvider')]
    public function testInvalidRequestThrowsException(array $data, array $expectedViolations): void
    {
        try {
            CreateUserValidator::validateCreateUserRequest($data);
            $this->fail('Expected ValidatorException was not thrown');
        } catch (ValidatorException $e) {
            $violations = $e->getViolations();
            $this->assertNotNull($violations, 'Violations should not be null');
            $this->assertViolations($expectedViolations, $violations);
        }
    }

    /**
     * @return array<string, array{array<string, mixed>, array<string, string|array<string>>}>
     */
    public static function invalidRequestDataProvider(): array
    {
        return [
            'missing email' => [
                [
                    'password' => 'password123',
                    'password_confirmation' => 'password123',
                    'roles' => ['ROLE_USER'],
                ],
                ['[email]' => 'This field is missing.'],
            ],
            'missing password' => [
                [
                    'email' => 'test@example.com',
                    'roles' => ['ROLE_USER'],
                    'password_confirmation' => 'password123',
                ],
                [
                    '[password]' => 'This field is missing.',
                    '[password_confirmation]' => 'Passwords do not match.',
                ],
            ],
            'missing roles' => [
                [
                    'email' => 'test@example.com',
                    'password' => 'password123',
                    'password_confirmation' => 'password123',
                ],
                ['[roles]' => 'This field is missing.'],
            ],
            'missing password confirmation' => [
                [
                    'email' => 'test@example.com',
                    'password' => 'password123',
                    'roles' => ['ROLE_USER'],
                ],
                ['[password_confirmation]' => 'This field is missing.'],
            ],
            'invalid email format' => [
                [
                    'email' => 'invalid-email',
                    'password' => 'password123',
                    'password_confirmation' => 'password123',
                    'roles' => ['ROLE_USER'],
                ],
                ['[email]' => 'Invalid email format.'],
            ],
            'invalid role' => [
                [
                    'email' => 'test@example.com',
                    'password' => 'password123',
                    'password_confirmation' => 'password123',
                    'roles' => ['INVALID_ROLE'],
                ],
                ['[roles][0]' => 'Invalid role. Allowed values: ROLE_ADMIN, ROLE_USER.'],
            ],
            'empty roles array' => [
                [
                    'email' => 'test@example.com',
                    'password' => 'password123',
                    'password_confirmation' => 'password123',
                    'roles' => [],
                ],
                ['[roles]' => 'Roles are required.'],
            ],
            'blank email' => [
                [
                    'email' => '',
                    'password' => 'password123',
                    'password_confirmation' => 'password123',
                    'roles' => ['ROLE_USER'],
                ],
                ['[email]' => 'Email is required.'],
            ],
            'blank password' => [
                [
                    'email' => 'test@example.com',
                    'password' => '',
                    'password_confirmation' => '',
                    'roles' => ['ROLE_USER'],
                ],
                [
                    '[password]' => 'Password is required.',
                    '[password_confirmation]' => 'Password confirmation is required.',
                ],
            ],
        ];
    }
}
