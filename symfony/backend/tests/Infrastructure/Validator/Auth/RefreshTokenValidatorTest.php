<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Validator\Auth;

use App\Infrastructure\Validator\Auth\RefreshTokenValidator;
use App\Infrastructure\Validator\ValidatorException;
use App\Tests\Infrastructure\Validator\ValidatorTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class RefreshTokenValidatorTest extends ValidatorTestCase
{
    /**
     * @param array<string, mixed> $data
     *
     * @throws ValidatorException
     */
    #[DataProvider('validRequestDataProvider')]
    public function testValidRequestDoesNotThrowException(array $data): void
    {
        RefreshTokenValidator::validateRefreshTokenRequest($data);

        $this->expectNotToPerformAssertions();
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function validRequestDataProvider(): array
    {
        return [
            'basic token' => [
                ['refresh_token' => 'refresh-token'],
            ],
            'long token' => [
                ['refresh_token' => str_repeat('a', 128)],
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
            RefreshTokenValidator::validateRefreshTokenRequest($data);
            $this->fail('Expected ValidatorException was not thrown');
        } catch (ValidatorException $exception) {
            $violations = $exception->getViolations();
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
            'missing refresh_token' => [
                [],
                ['[refresh_token]' => 'This field is missing.'],
            ],
            'blank refresh_token' => [
                ['refresh_token' => ''],
                ['[refresh_token]' => 'Missing refresh_token parameter'],
            ],
            'null refresh_token' => [
                ['refresh_token' => null],
                [
                    '[refresh_token]' => [
                        'Missing refresh_token parameter',
                        'This value should be of type string.',
                    ],
                ],
            ],
            'non string refresh_token' => [
                ['refresh_token' => 123],
                ['[refresh_token]' => 'This value should be of type string.'],
            ],
        ];
    }
}
