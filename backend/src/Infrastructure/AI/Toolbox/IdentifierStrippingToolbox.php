<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Toolbox;

use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\Tool;

/**
 * The only sanitizing mechanism for tool payloads: it decorates the toolbox so every tool is covered
 * without being asked. It only handles array results (see ToolPayloadSanitizer for the rules and limits);
 * other results are passed through unchanged.
 *
 * Wiring over a real toolbox is registered by StripIdentifiersFromToolboxesPass and verified with #105.
 */
final readonly class IdentifierStrippingToolbox implements ToolboxInterface
{
    public function __construct(
        private ToolboxInterface $inner,
    ) {
    }

    /**
     * @return Tool[]
     */
    public function getTools(): array
    {
        return $this->inner->getTools();
    }

    public function execute(ToolCall $toolCall): ToolResult
    {
        $result = $this->inner->execute($toolCall);
        $payload = $result->getResult();

        if (!\is_array($payload)) {
            return $result;
        }

        return new ToolResult(
            $result->getToolCall(),
            ToolPayloadSanitizer::withoutIds($payload),
            $result->getSources(),
        );
    }
}
