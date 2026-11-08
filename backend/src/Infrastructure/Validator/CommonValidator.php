<?php

declare(strict_types=1);

namespace App\Infrastructure\Validator;

use Symfony\Component\Validator\ValidatorBuilder;

class CommonValidator
{
    /**
     * @param array<string, mixed> $payload
     *
     * @throws ValidatorException
     */
    public static function validate(array $payload, mixed $constraints): void
    {
        $validator = (new ValidatorBuilder())->getValidator();
        $violations = $validator->validate($payload, $constraints);

        if ($violations->count() > 0) {
            throw ValidatorException::createByViolations($violations);
        }
    }
}
