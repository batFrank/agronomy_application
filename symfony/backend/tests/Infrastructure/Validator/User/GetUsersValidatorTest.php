<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Validator\User;

use App\Infrastructure\Validator\User\GetUsersValidator;
use App\Infrastructure\Validator\ValidatorException;
use App\Tests\Infrastructure\Validator\ValidatorTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class GetUsersValidatorTest extends ValidatorTestCase
{
    /**
     * @param array<string, mixed> $data
     *
     * @throws ValidatorException
     */
    #[DataProvider('validRequestDataProvider')]
    public function testValidRequestDoesNotThrowException(array $data): void
    {
        GetUsersValidator::validateGetUsersRequest($data);

        $this->expectNotToPerformAssertions();
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function validRequestDataProvider(): array
    {
        return [
            'empty request' => [
                [],
            ],
            'with page' => [
                ['page' => 1],
            ],
            'with search' => [
                ['search' => 'john'],
            ],
            'with role filter' => [
                ['role' => 'ROLE_USER'],
            ],
            'with role admin' => [
                ['role' => 'ROLE_ADMIN'],
            ],
            'with role manager' => [
                ['role' => 'ROLE_MANAGER'],
            ],
            'with created_by filter' => [
                ['created_by' => '550e8400-e29b-41d4-a716-446655440000'],
            ],
            'with sort_by and sort_dir asc' => [
                [
                    'sort_by' => 'email',
                    'sort_dir' => 'asc',
                ],
            ],
            'with sort_by and sort_dir desc' => [
                [
                    'sort_by' => 'name',
                    'sort_dir' => 'desc',
                ],
            ],
            'with all parameters' => [
                [
                    'page' => 2,
                    'search' => 'test',
                    'role' => 'ROLE_ADMIN',
                    'created_by' => '550e8400-e29b-41d4-a716-446655440000',
                    'sort_by' => 'email',
                    'sort_dir' => 'asc',
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
            GetUsersValidator::validateGetUsersRequest($data);
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
            'page less than 1' => [
                ['page' => 0],
                ['[page]' => 'Page must be greater than 0.'],
            ],
            'page negative' => [
                ['page' => -1],
                ['[page]' => 'Page must be greater than 0.'],
            ],
            'invalid page type' => [
                ['page' => 'invalid'],
                ['[page]' => 'This value should be of type integer.'],
            ],
            'invalid role' => [
                ['role' => 'INVALID_ROLE'],
                ['[role]' => 'The value you selected is not a valid choice.'],
            ],
            'invalid sort direction' => [
                [
                    'sort_by' => 'email',
                    'sort_dir' => 'invalid',
                ],
                ['[sort_dir]' => 'The value you selected is not a valid choice.'],
            ],
            'sort_by without sort_dir' => [
                ['sort_by' => 'email'],
                ['[sort_by]' => 'Sort direction is required when sort by is provided.'],
            ],
            'sort_dir without sort_by' => [
                ['sort_dir' => 'asc'],
                ['[sort_dir]' => 'Sort by is required when sort direction is provided.'],
            ],
            'invalid created_by uuid' => [
                ['created_by' => 'invalid-uuid'],
                ['[created_by]' => 'This is not a valid UUID.'],
            ],
            'blank created_by' => [
                ['created_by' => ''],
                [
                    '[created_by]' => [
                        'This value should not be blank.',
                        'This is not a valid UUID.',
                    ],
                ],
            ],
            'blank search' => [
                ['search' => ''],
                ['[search]' => 'This value should not be blank.'],
            ],
            'blank role' => [
                ['role' => ''],
                [
                    '[role]' => [
                        'This value should not be blank.',
                        'The value you selected is not a valid choice.',
                    ],
                ],
            ],
            'blank sort_by without sort_dir' => [
                ['sort_by' => ''],
                [
                    '[sort_by]' => [
                        'This value should not be blank.',
                        'Sort direction is required when sort by is provided.',
                    ],
                ],
            ],
            'blank sort_dir without sort_by' => [
                ['sort_dir' => ''],
                [
                    '[sort_dir]' => [
                        'This value should not be blank.',
                        'The value you selected is not a valid choice.',
                        'Sort by is required when sort direction is provided.',
                    ],
                ],
            ],
            'invalid search type' => [
                ['search' => 123],
                ['[search]' => 'This value should be of type string.'],
            ],
            'invalid sort_by type' => [
                [
                    'sort_by' => 123,
                    'sort_dir' => 'asc',
                ],
                ['[sort_by]' => 'This value should be of type string.'],
            ],
        ];
    }
}
