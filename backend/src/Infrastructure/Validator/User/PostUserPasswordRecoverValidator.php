<?php

declare(strict_types=1);

namespace App\Infrastructure\Validator\User;

use App\Infrastructure\Validator\CommonValidator;
use App\Infrastructure\Validator\ValidatorException;
use Symfony\Component\Validator\Constraints as Assert;

class PostUserPasswordRecoverValidator
{
    /**
     * @param array<string, mixed> $payload
     *
     * @throws ValidatorException
     */
    public static function validatePostUserPasswordRecoverRequest(array $payload): void
    {
        $constraints = new Assert\Collection([
            'email' => [
                new Assert\NotBlank(message: 'Email is required'),
                new Assert\Email(message: 'Email must be a valid email address'),
            ],
        ]);

        CommonValidator::validate($payload, $constraints);
    }
}
