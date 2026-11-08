<?php

declare(strict_types=1);

namespace App\Application\QueryHandler;

use App\Application\AI\AgentAnswer;
use App\Application\AI\AgentGateway;
use App\Application\AI\AgentUnavailableException;
use App\Application\Query\AskAgent;

readonly class AskAgentQueryHandler
{
    public function __construct(private AgentGateway $agentGateway)
    {
    }

    /**
     * @throws AgentUnavailableException
     */
    public function handle(AskAgent $query): AgentAnswer
    {
        return $this->agentGateway->ask($query->getActor(), $query->getConversation());
    }
}
