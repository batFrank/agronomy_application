<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Validator\User;

use App\Infrastructure\Validator\User\UpdateUserValidator;
use App\Infrastructure\Validator\ValidatorException;
use App\Tests\Infrastructure\Validator\ValidatorTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class UpdateUserValidatorTest extends ValidatorTestCase
{
    /**
     * @param array<string, mixed> $data
     *
     * @throws ValidatorException
     */
    #[DataProvider('validRequestDataProvider')]
    public function testValidRequestDoesNotThrowException(array $data): void
    {
        $this->expectNotToPerformAssertions();
        UpdateUserValidator::validateUpdateUserRequest($data);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function validRequestDataProvider(): array
    {
        return [
            'empty request (no changes)' => [
                [],
            ],
            'only email' => [
                [
                    'email' => 'newemail@example.com',
                ],
            ],
            'only roles' => [
                [
                    'roles' => ['ROLE_USER'],
                ],
            ],
            'only password with confirmation' => [
                [
                    'password' => 'newpassword123',
                    'password_confirmation' => 'newpassword123',
                ],
            ],
            'email and roles' => [
                [
                    'email' => 'updated@example.com',
                    'roles' => ['ROLE_USER', 'ROLE_ADMIN'],
                ],
            ],
            'all fields' => [
                [
                    'email' => 'complete@example.com',
                    'password' => 'securepassword',
                    'password_confirmation' => 'securepassword',
                    'roles' => ['ROLE_ADMIN'],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed>                       $data
     * @param string|array<string, string|array<string>> $expectedError
     */
    #[DataProvider('invalidRequestDataProvider')]
    public function testInvalidRequestThrowsException(array $data, string|array $expectedError): void
    {
        try {
            UpdateUserValidator::validateUpdateUserRequest($data);
            $this->fail('Expected ValidatorException was not thrown');
        } catch (ValidatorException $e) {
            if (\is_string($expectedError)) {
                $this->assertEquals($expectedError, $e->getMessage());
            } else {
                $this->assertNotNull($e->getViolations(), 'Violations should not be null');
                $this->assertViolations($expectedError, $e->getViolations());
            }
        }
    }

    /**
     * @return array<string, array{array<string, mixed>, string|array<string, string|array<string>>}>
     */
    public static function invalidRequestDataProvider(): array
    {
        return [
            'invalid email format' => [
                [
                    'email' => 'invalid-email',
                ],
                ['[email]' => 'Invalid email format.'],
            ],
            'blank email' => [
                [
                    'email' => '',
                ],
                ['[email]' => 'Email cannot be blank.'],
            ],
            'password too short' => [
                [
                    'password' => '12345',
                    'password_confirmation' => '12345',
                ],
                ['[password]' => 'Password must be at least 6 characters.'],
            ],
            'blank password' => [
                [
                    'password' => '',
                    'password_confirmation' => '',
                ],
                ['[password]' => [
                    'Password cannot be blank.',
                    'Password must be at least 6 characters.',
                ]],
            ],
            'password without confirmation' => [
                [
                    'password' => 'newpassword123',
                ],
                'Password confirmation is required when updating password.',
            ],
            'password mismatch' => [
                [
                    'password' => 'newpassword123',
                    'password_confirmation' => 'differentpassword',
                ],
                'Passwords do not match.',
            ],
            'invalid role' => [
                [
                    'roles' => ['INVALID_ROLE'],
                ],
                ['[roles][0]' => 'Invalid role. Allowed values: ROLE_ADMIN, ROLE_USER.'],
            ],
            'empty roles array' => [
                [
                    'roles' => [],
                ],
                ['[roles]' => 'Roles cannot be blank.'],
            ],
            'extra field not allowed' => [
                [
                    'unknown_field' => 'value',
                ],
                ['[unknown_field]' => 'This field was not expected.'],
            ],
        ];
    }
}
