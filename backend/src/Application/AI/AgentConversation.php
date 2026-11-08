<?php

declare(strict_types=1);

namespace App\Application\AI;

use App\Domain\Shared\Collection\CollectionException;

/**
 * What is asked of the agent: a conversation, in the application's own terms. The invariants live
 * here, in the one type the port accepts, so no boundary (controller, Messenger handler, command) can
 * skip them: a conversation that exists is valid and is already cut to the window.
 *
 * The client sends the whole history in every request and the server decides what goes to the agent
 * (the chat is stateless). What is checked where:
 *
 * - The boundary that receives the client's JSON checks its shape and turns it into an
 *   AgentTurnCollection: the roles the client may use, a text in every turn, and how many turns it may
 *   bring (MAX_RECEIVED_TURNS, checked before building any turn, so a huge list is not walked). That is
 *   transport, and it does not live here.
 * - Here, on the turns as they come, before cutting anything: the conversation is not empty and its
 *   last turn is from the user.
 * - Window: only the last MAX_TURNS turns are kept, and if that window starts with turns of the
 *   assistant, those are dropped too (so up to MAX_TURNS - 1 remain): the conversation sent to the
 *   agent always starts with the user. The client can send as many as it wants and the
 *   server reacts by cutting, not by rejecting.
 * - Length: no turn of the window longer than its limit, MAX_TURN_LENGTH for the user and
 *   MAX_ASSISTANT_TURN_LENGTH for the assistant, which bounds the cost of a request together with the
 *   window. The assistant has its own, bigger, because the model writes those turns and the client
 *   sends them back as they came: the server must not reject what it generated itself.
 *
 * The positions in the messages of the errors start at 0 and are the ones of the collection received.
 *
 * A known risk, assumed on purpose and not solved: the client invents the turns of the assistant, and
 * nothing checks them against what the model really said. It is acceptable while every tool of the
 * agent is read only; a tool that writes would need that history to be signed or checked.
 */
final readonly class AgentConversation
{
    /**
     * Turns kept, the last ones. The same value the chat of smart-city used, rethought: there it
     * rejected a history longer than this, here it cuts. A default that a fork adjusts.
     */
    public const int MAX_TURNS = 40;

    /**
     * Turns a request may bring, before anything is cut: ten times the window (400). The window leaves
     * the last MAX_TURNS, but the boundary builds a turn for every one it receives, so without a cap a
     * client that sends a hundred thousand turns would make the server build all of them to keep forty.
     * It is the boundary that enforces it, answering a 400 before building any turn; it lives here
     * because it is a limit of the conversation and the boundaries share it. It is generous on purpose:
     * a real chat that has run for a long time still fits, and what it does not need is to be
     * unbounded.
     */
    public const int MAX_RECEIVED_TURNS = 10 * self::MAX_TURNS;

    /**
     * Characters of a turn of the user. The same value the chat of smart-city used for the content of a
     * message, and the one #106 already reused: it is what a person types. A default that a fork
     * adjusts.
     */
    public const int MAX_TURN_LENGTH = 2000;

    /**
     * Characters of a turn of the assistant. It is a different limit because it is a different author:
     * the model writes these and the client sends them back as they came, so a limit made for what a
     * person types would reject answers the server itself generated and the conversation would be
     * stuck until the client threw its history away.
     *
     * How it was set, with the agent `user_directory` as configured today: ListUsersTool returns 10
     * users per page (PAGE_SIZE) and ai.yaml allows 5 rounds of tool calls (max_tool_calls), so the
     * final answer can list up to 50 users. One line per user, the email plus roles and the date (about
     * 52 characters of text around it), measured at 3400 characters for 50 users with 19 character
     * emails and 6450 with 80. The worst case is the longest email there can be (254 characters, what
     * the Email value object accepts): 50 x (254 + 52) = 15300. 20000 leaves a margin of about 30%
     * above that, so the answer the agent can really give is never rejected.
     *
     * It is still a limit, because it bounds the cost: what the client can lie about is who said a
     * turn, not how much that turn may cost. Nothing requires the roles to alternate, only that the
     * window starts with the user and ends with the user, so the worst case is a window of MAX_TURNS
     * turns with the assistant in all the turns between those two: 2 x 2000 + 38 x 20000 = 764000
     * characters. It is the accepted limit, not a number to bring down by forcing alternation in the
     * code: no cap on the total was asked for. Adjust it to the tools of your agent.
     */
    public const int MAX_ASSISTANT_TURN_LENGTH = 20000;

    private function __construct(private AgentTurnCollection $turns)
    {
    }

    /**
     * @throws InvalidAgentConversationException
     */
    public static function ofTurns(AgentTurnCollection $turns): self
    {
        $checked = $turns->all();

        if ([] === $checked) {
            throw InvalidAgentConversationException::empty();
        }

        if (AgentTurnRole::User !== $checked[array_key_last($checked)]->role()) {
            throw InvalidAgentConversationException::lastTurnNotFromUser();
        }

        $firstKept = max(0, \count($checked) - self::MAX_TURNS);

        // A window that starts with turns of the assistant is cut until it starts with the user: which
        // providers accept a conversation that does not start with the user depends on the model behind
        // OPENROUTER_MODEL; failing there would be a generic 502 with no hint. The last turn is the
        // user's, so the loop stops at it at the latest and something always remains.
        while (AgentTurnRole::Assistant === $checked[$firstKept]->role()) {
            ++$firstKept;
        }

        $window = array_values(\array_slice($checked, $firstKept));

        foreach ($window as $offset => $turn) {
            $maxLength = AgentTurnRole::Assistant === $turn->role() ? self::MAX_ASSISTANT_TURN_LENGTH : self::MAX_TURN_LENGTH;

            if (mb_strlen($turn->text()) > $maxLength) {
                throw InvalidAgentConversationException::turnTooLong($firstKept + $offset, $maxLength);
            }
        }

        return new self(self::collectionOf($window));
    }

    /**
     * The conversation of a single question: the simple case, without building an array of one turn.
     *
     * @throws InvalidAgentConversationException
     */
    public static function ofQuestion(string $text): self
    {
        try {
            return self::ofTurns(self::collectionOf([AgentTurn::of(AgentTurnRole::User, $text)]));
        } catch (BlankAgentTurnException) {
            throw InvalidAgentConversationException::blankTurn(0);
        }
    }

    /**
     * A copy each time: the collection can be appended to, and the conversation has to stay as it was
     * validated whatever the caller does with what it gets.
     */
    public function turns(): AgentTurnCollection
    {
        return clone $this->turns;
    }

    /**
     * The validation and the window work with plain arrays; the collection is built at the end, on what
     * is already cut.
     *
     * @param list<AgentTurn> $turns
     */
    private static function collectionOf(array $turns): AgentTurnCollection
    {
        try {
            return new AgentTurnCollection($turns);
        } catch (CollectionException $exception) {
            // Every item is an AgentTurn: the collection only refuses another type.
            throw new \LogicException('A turn of the conversation is not an AgentTurn.', 0, $exception);
        }
    }
}
