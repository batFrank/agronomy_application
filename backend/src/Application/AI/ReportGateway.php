<?php

declare(strict_types=1);

namespace App\Application\AI;

use App\Application\Security\User as SecurityUser;

/**
 * Port to the generation agent: the second form of use of the agents, a call with no conversation
 * that asks for an output of a fixed shape instead of free text. Like AgentGateway it is a technical
 * capability the application orchestrates, so it lives in Application, and no type of the provider's
 * SDK crosses it.
 *
 * It takes a string and not an AgentConversation on purpose: this is generation in one shot, and the
 * invariants of the conversation (the window, the length of a turn, who speaks last) belong to the
 * chat, not to this.
 *
 * The same requirements as AgentGateway for an implementation: the actor is made available while the
 * agent runs and released afterwards, the result comes back already resolved (the provider's
 * execution is lazy), and a call made while another one is running fails.
 */
interface ReportGateway
{
    /**
     * Any failure of the provider or of the agent loop is reported as an AgentUnavailableException,
     * and so is an output that does not have the shape asked for (not JSON, or JSON that does not fit
     * the schema or breaks its constraints): its message is generic, and the real cause travels in
     * `previous`. The consumer gets a Report or that exception, never a malformed output.
     *
     * @throws AgentUnavailableException
     * @throws \LogicException           when another call is already running in this context
     */
    public function generate(SecurityUser $actor, string $instructions): Report;
}
