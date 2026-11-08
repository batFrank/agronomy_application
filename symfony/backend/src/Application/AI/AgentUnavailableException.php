<?php

declare(strict_types=1);

namespace App\Application\AI;

use App\Domain\Shared\Exception\BadGatewayException;

/**
 * The agent could not give an answer because what is behind it failed. The message is what the
 * client sees: generic on purpose, it names no provider and repeats nothing of the failure. The real
 * cause goes in `previous`, and reaches the log through LoggableException. How it is shown (the
 * status code) is the business of the layer that handles it, see BadGatewayException.
 *
 * Today every failure of the adapter ends here, the provider rejecting the content of the question
 * included (a context too long, a filtered content): those are the client's, not the provider being
 * down, and telling them apart is #127.
 */
final class AgentUnavailableException extends BadGatewayException
{
    private const string UNUSABLE_ANSWER = 'The assistant did not give a usable answer.';

    /**
     * The provider, the HTTP transport or the agent loop failed.
     */
    public static function fromProviderFailure(\Throwable $previous): self
    {
        return new self('The assistant is not available at the moment.', 0, $previous);
    }

    /**
     * The agent answered, but with an empty text (blank counts as empty). There is no throwable to
     * wrap, so the cause is built here and tells the log what happened, without the client seeing it.
     * The caller checks that the text is empty; this factory cannot.
     */
    public static function emptyAnswer(): self
    {
        return new self(
            self::UNUSABLE_ANSWER,
            0,
            new \UnexpectedValueException('The agent answered with an empty text.'),
        );
    }

    /**
     * The agent answered, but not with text. The type excludes a string on purpose: what is a string
     * is either text or empty, and it never gets here, so a string cannot leave a false cause in the
     * log. The cause names the type that was received, without the client seeing it.
     *
     * @param int|float|bool|array<mixed>|object|null $content
     */
    public static function nonTextAnswer(int|float|bool|array|object|null $content): self
    {
        return new self(
            self::UNUSABLE_ANSWER,
            0,
            new \UnexpectedValueException(\sprintf('The agent answered with %s instead of text.', get_debug_type($content))),
        );
    }
}
