<?php

declare(strict_types=1);

namespace App\Tests\Mother\Application\AI;

use App\Application\AI\AgentTurn;
use App\Application\AI\AgentTurnCollection;
use App\Domain\Shared\Collection\CollectionException;

class AgentTurnCollectionMother
{
    /**
     * @param array<AgentTurn> $turns
     *
     * @throws CollectionException
     */
    public static function fromList(array $turns): AgentTurnCollection
    {
        return new AgentTurnCollection($turns);
    }
}
