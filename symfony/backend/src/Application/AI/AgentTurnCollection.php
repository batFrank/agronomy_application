<?php

declare(strict_types=1);

namespace App\Application\AI;

use App\Domain\Shared\Collection\Collection;

/**
 * The turns of an AgentConversation, typed like the other collections of the project.
 *
 * @extends Collection<AgentTurn>
 */
class AgentTurnCollection extends Collection
{
    #[\Override]
    public function getTypeName(): string
    {
        return AgentTurn::class;
    }
}
