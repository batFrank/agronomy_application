<?php

declare(strict_types=1);

namespace App\Application\AI;

use App\Domain\Shared\Exception\BadRequestException;

/**
 * The conversation sent is not valid: the caller's mistake, answered with a 400. The messages name the
 * position of the turn (the one in the collection received, counting from 0) and never repeat what it said.
 */
final class InvalidAgentConversationException extends BadRequestException
{
    public static function empty(): self
    {
        return new self('The conversation must have at least one turn.');
    }

    public static function blankTurn(int $position): self
    {
        return new self(\sprintf('Turn %d cannot be empty.', $position));
    }

    public static function lastTurnNotFromUser(): self
    {
        return new self('The last turn of the conversation must be from the user.');
    }

    public static function turnTooLong(int $position, int $maxLength): self
    {
        return new self(\sprintf('Turn %d cannot be longer than %d characters.', $position, $maxLength));
    }
}
