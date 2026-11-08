<?php

declare(strict_types=1);

namespace App\Application\Query;

use App\Application\AI\AgentConversation;
use App\Application\Security\User as SecurityUser;
use App\Domain\Bus\Query;

/**
 * The actor travels in the query and is required: there is no way to ask the agent without saying on
 * whose behalf. The conversation is an AgentConversation, which cannot be built malformed or oversized. See
 * AgentGateway.
 */
readonly class AskAgent implements Query
{
    public function __construct(
        private SecurityUser $actor,
        private AgentConversation $conversation,
    ) {
    }

    public function getActor(): SecurityUser
    {
        return $this->actor;
    }

    public function getConversation(): AgentConversation
    {
        return $this->conversation;
    }
}
