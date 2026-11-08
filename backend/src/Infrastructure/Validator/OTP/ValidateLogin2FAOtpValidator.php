<?php

declare(strict_types=1);

namespace App\Infrastructure\Validator\OTP;

use App\Infrastructure\Validator\CommonValidator;
use App\Infrastructure\Validator\ValidatorException;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class ValidateLogin2FAOtpValidator extends CommonValidator
{
    /**
     * @param array<string, mixed> $payload
     *
     * @throws ValidatorException
     */
    public static function validateValidateLogin2FAOtpRequest(array $payload): void
    {
        $constraints = new Collection([
            'email' => [
                new NotBlank(message: 'Email is required.'),
                new Type('string'),
                new Email(message: 'Invalid email format.'),
            ],
            'code' => [
                new NotBlank(message: 'Code should not be blank.'),
                new Type('string'),
            ],
        ]);

        self::validate($payload, $constraints);
    }
}
