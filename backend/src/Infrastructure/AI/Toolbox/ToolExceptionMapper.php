<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Toolbox;

use App\Infrastructure\AI\Toolbox\Translator\ToolExceptionTranslator;

final readonly class ToolExceptionMapper
{
    /** @param iterable<ToolExceptionTranslator> $translators */
    public function __construct(private iterable $translators)
    {
    }

    public function toToolCallException(\Throwable $exception): ToolCallException
    {
        if ($exception instanceof ToolCallException) {
            return $exception;
        }

        foreach ($this->translators as $translator) {
            if ($translator->supports($exception)) {
                return ToolCallException::withReason($translator->translate($exception), $exception);
            }
        }

        return ToolCallException::unexpected($exception);
    }
}
