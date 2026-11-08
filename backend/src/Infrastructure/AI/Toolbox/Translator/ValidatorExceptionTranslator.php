<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Toolbox\Translator;

use App\Infrastructure\Validator\ValidatorException;

final readonly class ValidatorExceptionTranslator implements ToolExceptionTranslator
{
    public function supports(\Throwable $exception): bool
    {
        return $exception instanceof ValidatorException;
    }

    /**
     * Lists property paths and messages only: the rejected value is never included.
     *
     * @throws \LogicException when the exception is not a ValidatorException
     */
    public function translate(\Throwable $exception): string
    {
        if (!$exception instanceof ValidatorException) {
            throw new \LogicException(\sprintf('ValidatorExceptionTranslator cannot translate %s', $exception::class));
        }

        $violations = $exception->getViolations();
        if (null === $violations || 0 === $violations->count()) {
            return $exception->getMessage();
        }

        $lines = ['The input is not valid.'];
        foreach ($violations as $violation) {
            $lines[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
        }

        return implode("\n", $lines);
    }
}
