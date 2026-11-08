<?php

declare(strict_types=1);

namespace App\Infrastructure\Validator\User;

use App\Domain\User\Exception\InvalidPasswordException;
use App\Infrastructure\Validator\CommonValidator;
use App\Infrastructure\Validator\ValidatorException;
use Symfony\Component\Validator\Constraints as Assert;

class PostUserPasswordResetValidator
{
    /**
     * @param array<string, mixed> $payload
     *
     * @throws ValidatorException
     */
    public static function validatePostUserPasswordResetRequest(array $payload): void
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
            'new_password' => [
                new Assert\NotBlank(message: 'New password is required'),
                new Assert\Type(type: 'string', message: 'New password must be a string'),
                new Assert\Length(
                    min: InvalidPasswordException::MIN_LENGTH,
                    max: 255,
                    minMessage: 'Password must be at least {{ limit }} characters long',
                    maxMessage: 'Password cannot be longer than {{ limit }} characters',
                ),
            ],
        ]);

        CommonValidator::validate($payload, $constraints);
    }
}
