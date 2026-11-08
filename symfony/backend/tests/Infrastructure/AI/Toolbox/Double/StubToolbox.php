<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Toolbox\Double;

use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Result\ToolCall;

/**
 * Toolbox that answers every call with the payload it was built with.
 */
final readonly class StubToolbox implements ToolboxInterface
{
    /**
     * @param array<array-key, mixed> $payload
     */
    public function __construct(
        private array $payload,
    ) {
    }

    public function getTools(): array
    {
        return [];
    }

    public function execute(ToolCall $toolCall): ToolResult
    {
        return new ToolResult($toolCall, $this->payload);
    }
}
