<?php

declare(strict_types=1);

namespace App\Infrastructure\Validator\Uuid;

use App\Infrastructure\Validator\CommonValidator;
use App\Infrastructure\Validator\ValidatorException;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\Uuid;

class UuidValidator extends CommonValidator
{
    /**
     * @throws ValidatorException
     */
    public static function validateUuid(string $uuid): void
    {
        $constraints = new Collection([
            'uuid' => [
                new NotBlank(),
                new Type('string'),
                new Uuid(versions: [Uuid::V7_MONOTONIC]),
            ],
        ]);

        parent::validate(['uuid' => $uuid], $constraints);
    }
}
