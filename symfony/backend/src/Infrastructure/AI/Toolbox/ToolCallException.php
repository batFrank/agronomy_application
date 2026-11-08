<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Toolbox;

use Symfony\AI\Agent\Toolbox\Exception\ToolExecutionExceptionInterface;

final class ToolCallException extends \RuntimeException implements ToolExecutionExceptionInterface
{
    private const string UNEXPECTED_REASON = 'The operation could not be completed.';

    private function __construct(
        private readonly string $toolCallResult,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($toolCallResult, previous: $previous);
    }

    public static function withReason(string $reason, ?\Throwable $previous = null): self
    {
        return new self($reason, $previous);
    }

    public static function unexpected(\Throwable $previous): self
    {
        return new self(self::UNEXPECTED_REASON, $previous);
    }

    public function getToolCallResult(): string
    {
        return $this->toolCallResult;
    }
}
