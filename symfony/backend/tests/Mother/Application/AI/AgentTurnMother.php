<?php

declare(strict_types=1);

namespace App\Tests\Mother\Application\AI;

use App\Application\AI\AgentTurn;
use App\Application\AI\AgentTurnRole;
use App\Application\AI\BlankAgentTurnException;

class AgentTurnMother
{
    /**
     * @throws BlankAgentTurnException
     */
    public static function user(string $text = 'Who is there?'): AgentTurn
    {
        return AgentTurn::of(AgentTurnRole::User, $text);
    }

    /**
     * @throws BlankAgentTurnException
     */
    public static function assistant(string $text = 'Three people.'): AgentTurn
    {
        return AgentTurn::of(AgentTurnRole::Assistant, $text);
    }
}
