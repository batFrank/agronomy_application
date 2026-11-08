<?php

declare(strict_types=1);

namespace App\Application\AI;

/**
 * An AgentTurn cannot be built with a text that is empty after trim. The boundary that receives
 * the client's turns checks it first and answers a 400; whoever builds a turn by hand without that
 * check is making a programming mistake.
 */
final class BlankAgentTurnException extends \InvalidArgumentException
{
    public static function create(): self
    {
        return new self('The text of a turn cannot be empty.');
    }
}
