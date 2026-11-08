<?php

declare(strict_types=1);

namespace App\Infrastructure\Validator\User;

use App\Domain\User\Exception\InvalidPasswordException;
use App\Infrastructure\Validator\CommonValidator;
use App\Infrastructure\Validator\ValidatorException;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;

class UpdateUserValidator extends CommonValidator
{
    /**
     * @param array<string, mixed> $data
     *
     * @throws ValidatorException
     */
    public static function validateUpdateUserRequest(array $data): void
    {
        $constraints = new Collection(
            fields: [
                'email' => new Optional([
                    new NotBlank(message: 'Email cannot be blank.'),
                    new Type('string'),
                    new Email(message: 'Invalid email format.'),
                ]),
                'password' => new Optional([
                    new NotBlank(message: 'Password cannot be blank.'),
                    new Type('string'),
                    new Length(min: InvalidPasswordException::MIN_LENGTH, minMessage: 'Password must be at least {{ limit }} characters.'),
                ]),
                'password_confirmation' => new Optional([
                    new Type('string'),
                ]),
                'roles' => new Optional([
                    new NotBlank(message: 'Roles cannot be blank.'),
                    new Type('array'),
                    new All([
                        new Type('string'),
                        new Choice(
                            choices: ['ROLE_ADMIN', 'ROLE_USER'],
                            message: 'Invalid role. Allowed values: ROLE_ADMIN, ROLE_USER.',
                        ),
                    ]),
                ]),
            ],
            allowExtraFields: false,
        );

        self::validate($data, $constraints);

        // Validate password confirmation if the password is provided
        if (isset($data['password'])) {
            if (!isset($data['password_confirmation'])) {
                throw ValidatorException::createByMessage('Password confirmation is required when updating password.');
            }
            if ($data['password'] !== $data['password_confirmation']) {
                throw ValidatorException::createByMessage('Passwords do not match.');
            }
        }
    }
}
