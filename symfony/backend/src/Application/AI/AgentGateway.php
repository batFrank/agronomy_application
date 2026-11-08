<?php

declare(strict_types=1);

namespace App\Application\AI;

use App\Application\Security\User as SecurityUser;

/**
 * Port to the agent: a technical capability the application orchestrates (like Logger or
 * TransactionManager), not a rule the domain needs, so it lives in Application. No type of the
 * provider's SDK crosses it.
 *
 * It is the one funnel every call to the agent goes through, from a controller, a Messenger handler
 * or a command. Whoever implements it must make the actor available to the tools while the agent
 * runs and release it afterwards, so no boundary can forget to do it.
 *
 * Requirements for an implementation:
 *  - the answer is returned already resolved, with the actor still available: the provider's
 *    execution is lazy, the tools only run when its content is read, and an actor released earlier
 *    leaves them without identity. A streamed answer, in the future, has to keep the actor entered
 *    for as long as it is iterated;
 *  - there is no mode without an actor: the actor is required. A call from a command builds one or
 *    fails when it builds the query;
 *  - a call made while another one is running fails (the context refuses a second actor).
 */
interface AgentGateway
{
    /**
     * Any failure of the provider (including the HTTP transport) or of the agent loop, and an answer
     * that is not text or is empty, are reported as an AgentUnavailableException: its message is
     * generic, and the real cause travels in `previous`.
     *
     * @throws AgentUnavailableException
     * @throws \LogicException           when another call is already running in this context
     */
    public function ask(SecurityUser $actor, AgentConversation $conversation): AgentAnswer;
}
