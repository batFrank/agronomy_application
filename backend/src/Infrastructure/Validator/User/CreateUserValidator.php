<?php

declare(strict_types=1);

namespace App\Infrastructure\Validator\User;

use App\Infrastructure\Validator\CommonValidator;
use App\Infrastructure\Validator\ValidatorException;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class CreateUserValidator extends CommonValidator
{
    /**
     * @param array<string, mixed> $data
     *
     * @throws ValidatorException
     */
    public static function validateCreateUserRequest(array $data): void
    {
        $constraints = new Collection([
            'email' => [
                new NotBlank(message: 'Email is required.'),
                new Type('string'),
                new Email(message: 'Invalid email format.'),
            ],
            'password' => [
                new NotBlank(message: 'Password is required.'),
                new Type('string'),
            ],
            'password_confirmation' => [
                new NotBlank(message: 'Password confirmation is required.'),
                new Type('string'),
                new Callback(callback: function ($passwordConfirmation, ExecutionContextInterface $context) {
                    $password = $context->getRoot()['password'] ?? '';
                    if ($password !== $passwordConfirmation) {
                        $context->buildViolation('Passwords do not match.')->addViolation();
                    }
                }),
            ],
            'roles' => [
                new NotBlank(message: 'Roles are required.'),
                new Type('array'),
                new All([
                    new Type('string'),
                    new Choice(
                        choices: ['ROLE_ADMIN', 'ROLE_USER'],
                        message: 'Invalid role. Allowed values: ROLE_ADMIN, ROLE_USER.',
                    ),
                ]),
            ],
        ]);

        self::validate($data, $constraints);
    }
}
