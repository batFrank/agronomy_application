<?php

declare(strict_types=1);

namespace App\Infrastructure\Validator\User;

use App\Infrastructure\Validator\CommonValidator;
use App\Infrastructure\Validator\ValidatorException;
use Symfony\Component\Validator\Constraints as Assert;

class PostUserPasswordCheckValidator
{
    /**
     * @param array<string, mixed> $payload
     *
     * @throws ValidatorException
     */
    public static function validatePostUserPasswordCheckRequest(array $payload): void
    {
        $constraints = new Assert\Collection([
            'reset_token' => [
                new Assert\NotBlank(message: 'Reset token is required'),
                new Assert\Type(type: 'string', message: 'Reset token must be a string'),
                new Assert\Length(
                    min: 32,
                    max: 128,
                    minMessage: 'Reset token must be at least {{ limit }} characters long',
                    maxMessage: 'Reset token cannot be longer than {{ limit }} characters',
                ),
            ],
        ]);

        CommonValidator::validate($payload, $constraints);
    }
}
