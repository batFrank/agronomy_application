<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Validator\OTP;

use App\Infrastructure\Validator\OTP\ValidateLogin2FAOtpValidator;
use App\Infrastructure\Validator\ValidatorException;
use App\Tests\Infrastructure\Validator\ValidatorTestCase;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversMethod(ValidateLogin2FAOtpValidator::class, 'validateValidateLogin2FAOtpRequest')]
class ValidateLogin2FAOtpValidatorTest extends ValidatorTestCase
{
    /**
     * @param array<string, mixed> $payload
     *
     * @throws ValidatorException
     */
    #[DataProvider('provideValidCases')]
    public function testValid(array $payload): void
    {
        $this->expectNotToPerformAssertions();
        ValidateLogin2FAOtpValidator::validateValidateLogin2FAOtpRequest($payload);
    }

    public static function provideValidCases(): \Generator
    {
        yield 'code is only numbers' => [
            'payload' => [
                'email' => 'example@test.com',
                'code' => '123456',
            ],
        ];

        yield 'code is alphanumeric' => [
            'payload' => [
                'email' => 'example@test.com',
                'code' => '12FA3X',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $violations
     */
    #[DataProvider('provideInvalidCases')]
    public function testInvalid(array $payload, array $violations): void
    {
        try {
            ValidateLogin2FAOtpValidator::validateValidateLogin2FAOtpRequest($payload);
            $this->fail('Expected ValidatorException was not thrown');
        } catch (ValidatorException $e) {
            $this->assertNotNull($e->getViolations(), 'Violations should not be null');
            $this->assertViolations($violations, $e->getViolations());
        }
    }

    public static function provideInvalidCases(): \Generator
    {
        yield 'empty body' => [
            'payload' => [],
            'violations' => [
                '[email]' => 'This field is missing.',
                '[code]' => 'This field is missing.',
            ],
        ];

        yield 'invalid email' => [
            'payload' => [
                'email' => 'invalid-email',
                'code' => '123456',
            ],
            'violations' => [
                '[email]' => 'Invalid email format.',
            ],
        ];

        yield 'empty code' => [
            'payload' => [
                'email' => 'test@example.com',
                'code' => '',
            ],
            'violations' => [
                '[code]' => 'Code should not be blank.',
            ],
        ];

        yield 'invalid datatype' => [
            'payload' => [
                'email' => 123456,
                'code' => 123456,
            ],
            'violations' => [
                '[email]' => ['Invalid email format.', 'This value should be of type string.'],
                '[code]' => ['This value should be of type string.'],
            ],
        ];
    }
}
