<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Toolbox\Translator;

use App\Domain\Shared\Exception\ForbiddenException;

final readonly class ForbiddenExceptionTranslator implements ToolExceptionTranslator
{
    public function supports(\Throwable $exception): bool
    {
        return $exception instanceof ForbiddenException;
    }

    public function translate(\Throwable $exception): string
    {
        $message = 'The current user is not allowed to perform this operation.';
        $reason = $exception->getMessage();

        if ('' === $reason || (new ForbiddenException())->getMessage() === $reason) {
            return $message;
        }

        return $message . ' Reason: ' . $reason;
    }
}
