<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Toolbox\Double;

use App\Domain\Bus\Command;
use App\Domain\Bus\Query;
use App\Infrastructure\AI\Toolbox\AgentTool;

/**
 * Exposes the protected bus gateways of AgentTool so unit tests can call them directly.
 */
final readonly class ProbeAgentTool extends AgentTool
{
    public function askQuery(Query $query): mixed
    {
        return $this->ask($query);
    }

    public function executeCommand(Command $command): mixed
    {
        return $this->execute($command);
    }
}
