<?php

declare(strict_types=1);

namespace App\Application\AI;

/**
 * One turn of an AgentConversation: who said it and what. A turn that exists has a role (the enum has
 * only the two the client may use) and a text that is not empty after trim, because there is no other
 * way to build one: the constructor is private and of() is the only door, and it checks.
 *
 * That is all a turn guarantees by itself. PHP cannot limit who calls of(), so what depends on the
 * conversation (the last turn is the user's, the window, the length of each turn, the position) is not
 * promised here but by AgentConversation, which is the one that builds the turns it keeps.
 */
final readonly class AgentTurn
{
    private function __construct(
        private AgentTurnRole $role,
        private string $text,
    ) {
    }

    /**
     * @throws BlankAgentTurnException
     */
    public static function of(AgentTurnRole $role, string $text): self
    {
        if ('' === trim($text)) {
            throw BlankAgentTurnException::create();
        }

        return new self($role, $text);
    }

    public function role(): AgentTurnRole
    {
        return $this->role;
    }

    public function text(): string
    {
        return $this->text;
    }
}
