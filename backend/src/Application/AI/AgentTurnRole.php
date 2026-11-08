<?php

declare(strict_types=1);

namespace App\Application\AI;

/**
 * Who said a turn of the conversation. Only these two exist for the application: the roles that the
 * client does not control (system, tool) are not representable, so they cannot be sent to the agent.
 *
 * It is not backed by the strings of any wire format: translating them is the job of the boundary
 * that receives the client's turns (the controller), which builds the AgentTurnCollection.
 */
enum AgentTurnRole
{
    case User;
    case Assistant;
}
