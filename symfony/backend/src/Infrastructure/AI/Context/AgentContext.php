<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Context;

use App\Application\Security\User as SecurityUser;

/**
 * Who started the current agent call. Tools read the actor from here and never from the
 * RequestStack: outside an HTTP request (a Messenger consumer, a cron, ai:agent:call) there is
 * no request nor security token. It is a mutable, execution-scoped service, like Symfony's
 * TokenStorage, so it must stay shared: every consumer has to see the same instance.
 *
 * Only the adapter of the AgentGateway port populates it (SymfonyAiAgentGateway): it calls enterAs()
 * before running the agent and leave() in a finally. A controller, a Messenger handler or a command
 * does not touch it: they ask the agent through the AskAgent query and the port takes care of the
 * rest. Populating it from a boundary as well would fail, because entering twice is refused.
 *
 * leave() exists because in a Messenger worker the container survives between messages: without
 * it, the actor of the previous message would leak into the next one.
 */
final class AgentContext
{
    private ?SecurityUser $actor = null;

    /**
     * Entering twice without leave() is a wiring error, not an override: overwriting silently
     * would hide a boundary that forgot to clear the context, and a nested leave() would wipe the
     * outer caller's actor.
     *
     * @throws \LogicException
     */
    public function enterAs(SecurityUser $actor): void
    {
        if (null !== $this->actor) {
            throw new \LogicException('AgentContext already has an actor: the previous call did not leave().');
        }

        $this->actor = $actor;
    }

    // Idempotent so it is always safe inside a finally block.
    public function leave(): void
    {
        $this->actor = null;
    }

    /**
     * Throws instead of returning null on purpose: a tool must never decide what to do without
     * an actor. It is not wrapped: it is thrown before the bus, so the toolbox turns it into its own
     * opaque generic message and the model never sees this text.
     *
     * @throws \LogicException
     */
    public function actor(): SecurityUser
    {
        return $this->actor ?? throw new \LogicException('AgentContext has no actor: the call was not started properly.');
    }
}
