<?php

declare(strict_types=1);

namespace App\Infrastructure\Validator\User;

use App\Infrastructure\Validator\CommonValidator;
use App\Infrastructure\Validator\ValidatorException;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\Uuid;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class GetUsersValidator extends CommonValidator
{
    /**
     * @param array<string, mixed> $data
     *
     * @throws ValidatorException
     */
    public static function validateGetUsersRequest(array $data): void
    {
        $constraints = new Collection([
            'page' => [
                new Optional([
                    new NotBlank(),
                    new Type('integer'),
                    new Callback(callback: function ($page, ExecutionContextInterface $context) {
                        if ($page < 1) {
                            $context->buildViolation('Page must be greater than 0.')->addViolation();
                        }
                    }),
                ]),
            ],
            'sort_by' => [
                new Optional([
                    new NotBlank(),
                    new Type('string'),
                    new Callback(callback: function ($sortBy, ExecutionContextInterface $context) {
                        if (!isset($context->getRoot()['sort_dir'])) {
                            $context->buildViolation(
                                'Sort direction is required when sort by is provided.'
                            )->addViolation();
                        }
                    }),
                ]),
            ],
            'sort_dir' => [
                new Optional([
                    new NotBlank(),
                    new Type('string'),
                    new Choice(
                        choices: [
                            'asc',
                            'desc',
                        ]
                    ),
                    new Callback(callback: function ($sortDir, ExecutionContextInterface $context) {
                        if (!isset($context->getRoot()['sort_by'])) {
                            $context->buildViolation(
                                'Sort by is required when sort direction is provided.'
                            )->addViolation();
                        }
                    }),
                ]),
            ],
            'search' => [
                new Optional([
                    new NotBlank(),
                    new Type('string'),
                ]),
            ],
            'role' => [
                new Optional([
                    new NotBlank(),
                    new Type('string'),
                    new Choice(
                        choices: [
                            'ROLE_ADMIN',
                            'ROLE_MANAGER',
                            'ROLE_USER',
                        ]
                    ),
                ]),
            ],
            'created_by' => [
                new Optional([
                    new NotBlank(),
                    new Type('string'),
                    new Uuid(),
                ]),
            ],
        ]);

        self::validate($data, $constraints);
    }
}
