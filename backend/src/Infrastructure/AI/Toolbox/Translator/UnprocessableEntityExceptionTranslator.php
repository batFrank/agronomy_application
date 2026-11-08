<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Toolbox\Translator;

use App\Domain\Shared\Exception\UnprocessableEntityException;

final readonly class UnprocessableEntityExceptionTranslator implements ToolExceptionTranslator
{
    public function supports(\Throwable $exception): bool
    {
        return $exception instanceof UnprocessableEntityException;
    }

    public function translate(\Throwable $exception): string
    {
        $message = 'The operation was rejected by a business rule.';
        $reason = $exception->getMessage();

        if ('' === $reason || (new UnprocessableEntityException())->getMessage() === $reason) {
            return $message;
        }

        return $message . ' Reason: ' . $reason;
    }
}
