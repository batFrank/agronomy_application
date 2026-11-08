<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Validator\OTP;

use App\Infrastructure\Validator\OTP\GenerateLogin2FAOtpValidator;
use App\Infrastructure\Validator\ValidatorException;
use App\Tests\Infrastructure\Validator\ValidatorTestCase;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversMethod(GenerateLogin2FAOtpValidator::class, 'validateGenerateLogin2FAOtpRequest')]
class GenerateLogin2FAOtpValidatorTest extends ValidatorTestCase
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
        GenerateLogin2FAOtpValidator::validateGenerateLogin2FAOtpRequest($payload);
    }

    public static function provideValidCases(): \Generator
    {
        yield 'only email' => [
            'payload' => [
                'email' => 'example@test.com',
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
            GenerateLogin2FAOtpValidator::validateGenerateLogin2FAOtpRequest($payload);
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
            ],
        ];

        yield 'invalid email' => [
            'payload' => [
                'email' => 'invalid-email',
            ],
            'violations' => [
                '[email]' => 'Invalid email format.',
            ],
        ];

        yield 'invalid datatype' => [
            'payload' => [
                'email' => 123456,
            ],
            'violations' => [
                '[email]' => ['Invalid email format.', 'This value should be of type string.'],
            ],
        ];
    }
}
