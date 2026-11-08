<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Toolbox\Translator;

use App\Domain\Shared\Exception\NotFoundException;

final readonly class NotFoundExceptionTranslator implements ToolExceptionTranslator
{
    public function supports(\Throwable $exception): bool
    {
        return $exception instanceof NotFoundException;
    }

    public function translate(\Throwable $exception): string
    {
        $message = 'The requested resource was not found.';
        $reason = $exception->getMessage();

        return '' === $reason ? $message : $message . ' Reason: ' . $reason;
    }
}
