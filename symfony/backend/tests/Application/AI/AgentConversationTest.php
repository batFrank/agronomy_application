<?php

declare(strict_types=1);

namespace App\Tests\Application\AI;

use App\Application\AI\AgentConversation;
use App\Application\AI\AgentTurn;
use App\Application\AI\AgentTurnCollection;
use App\Application\AI\AgentTurnRole;
use App\Application\AI\InvalidAgentConversationException;
use App\Domain\Shared\Collection\CollectionException;
use App\Domain\Shared\Email\Email;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Exception\BadRequestException;
use App\Tests\Mother\Application\AI\AgentTurnCollectionMother;
use App\Tests\Mother\Application\AI\AgentTurnMother;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AgentConversation::class)]
#[CoversClass(InvalidAgentConversationException::class)]
class AgentConversationTest extends TestCase
{
    /**
     * @throws InvalidAgentConversationException
     */
    public function testBuildsTheTurnsInOrderWithTheirRolesAndTextAsSent(): void
    {
        // Act
        $conversation = AgentConversation::ofTurns(AgentTurnCollectionMother::fromList([
            AgentTurnMother::user('Who is there?'),
            AgentTurnMother::assistant('Three people.'),
            AgentTurnMother::user('  And their roles?  '),
        ]));

        // Assert
        self::assertSame(
            [
                [AgentTurnRole::User, 'Who is there?'],
                [AgentTurnRole::Assistant, 'Three people.'],
                [AgentTurnRole::User, '  And their roles?  '],
            ],
            $this->asPairs($conversation),
        );
    }

    /**
     * @throws InvalidAgentConversationException
     */
    public function testAQuestionIsAConversationOfASingleUserTurn(): void
    {
        self::assertEquals(
            AgentConversation::ofTurns(AgentTurnCollectionMother::fromList([AgentTurnMother::user('Who is there?')])),
            AgentConversation::ofQuestion('Who is there?'),
        );
        self::assertSame([[AgentTurnRole::User, 'Who is there?']], $this->asPairs(AgentConversation::ofQuestion('Who is there?')));
    }

    public function testAnEmptyConversationIsRejected(): void
    {
        $this->expectException(InvalidAgentConversationException::class);
        $this->expectExceptionMessage('The conversation must have at least one turn.');

        AgentConversation::ofTurns(AgentTurnCollectionMother::fromList([]));
    }

    public function testAQuestionThatIsBlankAfterTrimIsRejected(): void
    {
        $this->expectException(InvalidAgentConversationException::class);
        $this->expectExceptionMessage('Turn 0 cannot be empty.');

        AgentConversation::ofQuestion("\t \n");
    }

    public function testAHistoryThatDoesNotEndWithTheUserIsRejected(): void
    {
        $this->expectException(InvalidAgentConversationException::class);
        $this->expectExceptionMessage('The last turn of the conversation must be from the user.');

        AgentConversation::ofTurns(AgentTurnCollectionMother::fromList([
            AgentTurnMother::user('Hi'),
            AgentTurnMother::assistant('Hello.'),
        ]));
    }

    /**
     * @throws InvalidAgentConversationException
     */
    public function testAHistoryLongerThanTheWindowIsCutToTheLastTurns(): void
    {
        // Arrange: twice the window, alternating roles, ending with the user.
        $turns = [];
        for ($i = 0; $i < 2 * AgentConversation::MAX_TURNS; ++$i) {
            $turns[] = (0 === $i % 2 ? AgentTurnMother::assistant('turn ' . $i) : AgentTurnMother::user('turn ' . $i));
        }

        // Act
        $conversation = AgentConversation::ofTurns(AgentTurnCollectionMother::fromList($turns));

        // Assert: it still works, with only the last ones, and in order. The window would start with the
        // assistant (turn 40), so that one is dropped as well.
        $kept = $this->asPairs($conversation);
        self::assertCount(AgentConversation::MAX_TURNS - 1, $kept);
        self::assertSame('turn ' . (AgentConversation::MAX_TURNS + 1), $kept[0][1]);
        self::assertSame('turn ' . (2 * AgentConversation::MAX_TURNS - 1), $kept[AgentConversation::MAX_TURNS - 2][1]);
    }

    /**
     * A window that starts with the user is kept whole: nothing is dropped.
     *
     * @throws InvalidAgentConversationException
     */
    public function testAWindowThatStartsWithTheUserKeepsAllItsTurns(): void
    {
        $turns = [];
        for ($i = 0; $i < AgentConversation::MAX_TURNS; ++$i) {
            $turns[] = AgentTurnMother::user('turn ' . $i);
        }

        self::assertCount(AgentConversation::MAX_TURNS, AgentConversation::ofTurns(AgentTurnCollectionMother::fromList($turns))->turns());
    }

    /**
     * A window that starts with the assistant loses that turn too, so the conversation that goes to the
     * agent starts with the user. It also happens without any cut: a greeting of the assistant at the
     * start of a short conversation is dropped.
     *
     * @throws InvalidAgentConversationException
     */
    public function testAConversationThatWouldStartWithTheAssistantStartsWithTheUser(): void
    {
        $conversation = AgentConversation::ofTurns(AgentTurnCollectionMother::fromList([
            AgentTurnMother::assistant('Hello, how can I help?'),
            AgentTurnMother::user('Who is there?'),
        ]));

        self::assertSame([[AgentTurnRole::User, 'Who is there?']], $this->asPairs($conversation));
    }

    /**
     * More than one turn of the assistant at the start of the window: all of them are dropped, not only
     * the first, so what goes to the agent starts with the user.
     *
     * @throws InvalidAgentConversationException
     */
    public function testSeveralTurnsOfTheAssistantAtTheStartOfTheWindowAreAllDropped(): void
    {
        $turns = [];
        for ($i = 0; $i < AgentConversation::MAX_TURNS - 2; ++$i) {
            $turns[] = AgentTurnMother::assistant('turn ' . $i);
        }
        $turns[] = AgentTurnMother::user('First question.');
        $turns[] = AgentTurnMother::user('Last question.');

        // Longer than the window, so it is cut first and the cut lands in a run of the assistant.
        array_unshift($turns, AgentTurnMother::user('Out of the window.'), AgentTurnMother::assistant('Out of the window.'));

        self::assertSame(
            [[AgentTurnRole::User, 'First question.'], [AgentTurnRole::User, 'Last question.']],
            $this->asPairs(AgentConversation::ofTurns(AgentTurnCollectionMother::fromList($turns))),
        );
    }

    /**
     * @throws InvalidAgentConversationException
     */
    public function testSeveralTurnsOfTheAssistantAtTheStartOfAShortConversationAreAllDropped(): void
    {
        $conversation = AgentConversation::ofTurns(AgentTurnCollectionMother::fromList([
            AgentTurnMother::assistant('Hello.'),
            AgentTurnMother::assistant('Anyone there?'),
            AgentTurnMother::user('Who is there?'),
        ]));

        self::assertSame([[AgentTurnRole::User, 'Who is there?']], $this->asPairs($conversation));
    }

    /**
     * The worst case that the limits allow, as documented in MAX_ASSISTANT_TURN_LENGTH: the roles do not
     * have to alternate, so a window of the user, 38 turns of the assistant at their limit and the user
     * is accepted, and weighs 764000 characters.
     *
     * @throws InvalidAgentConversationException
     */
    public function testTheDocumentedWorstCaseOfCostIsAccepted(): void
    {
        $turns = [AgentTurnMother::user(str_repeat('u', AgentConversation::MAX_TURN_LENGTH))];
        for ($i = 0; $i < AgentConversation::MAX_TURNS - 2; ++$i) {
            $turns[] = AgentTurnMother::assistant(str_repeat('a', AgentConversation::MAX_ASSISTANT_TURN_LENGTH));
        }
        $turns[] = AgentTurnMother::user(str_repeat('u', AgentConversation::MAX_TURN_LENGTH));

        $kept = AgentConversation::ofTurns(AgentTurnCollectionMother::fromList($turns))->turns();

        self::assertCount(AgentConversation::MAX_TURNS, $kept);
        self::assertSame(764000, array_sum(array_map(static fn (AgentTurn $turn): int => mb_strlen($turn->text()), $kept->all())));
    }

    /**
     * The turn that is dropped is not checked for length: it does not go anywhere.
     *
     * @throws InvalidAgentConversationException
     */
    public function testTheTurnThatTheWindowDropsIsNotCheckedForLength(): void
    {
        $turns = [AgentTurnMother::user('turn 0')];
        $turns[] = AgentTurnMother::assistant(str_repeat('a', AgentConversation::MAX_ASSISTANT_TURN_LENGTH + 1));
        for ($i = 2; $i <= AgentConversation::MAX_TURNS; ++$i) {
            $turns[] = AgentTurnMother::user('turn ' . $i);
        }

        // 41 turns: the window is the last 40, it starts with the long turn of the assistant and that is dropped.
        $kept = AgentConversation::ofTurns(AgentTurnCollectionMother::fromList($turns))->turns();

        self::assertCount(AgentConversation::MAX_TURNS - 1, $kept);
        self::assertSame(AgentTurnRole::User, $kept->all()[0]->role());
    }

    /**
     * The length limit applies after the cut: a turn that the window leaves out does not count.
     *
     * @throws InvalidAgentConversationException
     */
    public function testATurnThatTheWindowLeavesOutIsNotCheckedForLength(): void
    {
        $turns = [AgentTurnMother::assistant(str_repeat('a', AgentConversation::MAX_TURN_LENGTH + 1))];
        for ($i = 0; $i < AgentConversation::MAX_TURNS; ++$i) {
            $turns[] = (0 === $i % 2 ? AgentTurnMother::user('turn ' . $i) : AgentTurnMother::assistant('turn ' . $i));
        }
        // The window is the last 40 and the turn too long is the first of 41. The last one must be the user's.
        $turns[array_key_last($turns)] = AgentTurnMother::user('turn last');

        $conversation = AgentConversation::ofTurns(AgentTurnCollectionMother::fromList($turns));

        self::assertCount(AgentConversation::MAX_TURNS, $conversation->turns());
    }

    /**
     * @throws InvalidAgentConversationException
     */
    public function testATurnOfExactlyTheMaximumLengthIsAccepted(): void
    {
        $text = str_repeat('a', AgentConversation::MAX_TURN_LENGTH);

        self::assertSame([[AgentTurnRole::User, $text]], $this->asPairs(AgentConversation::ofQuestion($text)));
    }

    public function testATurnOneCharacterOverTheMaximumIsRejectedWithItsPosition(): void
    {
        $this->expectException(InvalidAgentConversationException::class);
        $this->expectExceptionMessage('Turn 2 cannot be longer than 2000 characters.');

        AgentConversation::ofTurns(AgentTurnCollectionMother::fromList([
            AgentTurnMother::user('Hi'),
            AgentTurnMother::assistant('Hello.'),
            AgentTurnMother::user(str_repeat('a', AgentConversation::MAX_TURN_LENGTH + 1)),
        ]));
    }

    /**
     * The assistant has its own limit, bigger: a turn the model wrote and the client sends back as it
     * came must not be rejected by a limit made for what a person types.
     *
     * @throws InvalidAgentConversationException
     */
    public function testATurnOfTheAssistantMayBeLongerThanOneOfTheUser(): void
    {
        $answer = str_repeat('a', AgentConversation::MAX_TURN_LENGTH + 1);

        $conversation = AgentConversation::ofTurns(AgentTurnCollectionMother::fromList([
            AgentTurnMother::user('Who is there?'),
            AgentTurnMother::assistant($answer),
            AgentTurnMother::user('Thanks.'),
        ]));

        self::assertSame($answer, $conversation->turns()->all()[1]->text());
    }

    /**
     * The answer the agent can really give, the worst case measured for `user_directory`: 50 users (10
     * per page, 5 rounds of tool calls), one line each, with the longest email there can be (254
     * characters). If it were rejected the conversation would be stuck.
     *
     * @throws InvalidAgentConversationException
     * @throws InvalidEmailException
     */
    public function testTheWorstAnswerTheAgentCanGiveIsAccepted(): void
    {
        // The longest email the Email value object accepts: 64 + 1 + labels of 63 characters = 254.
        $longestEmail = str_repeat('a', 64) . '@' . str_repeat('b', 63) . '.' . str_repeat('b', 63) . '.' . str_repeat('b', 57) . '.com';
        self::assertSame(254, \strlen($longestEmail));
        new Email($longestEmail);
        $answer = implode("\n", array_fill(0, 50, '- ' . $longestEmail . ' (ROLE_MANAGER, ROLE_USER), created 2026-01-01'));

        $conversation = AgentConversation::ofTurns(AgentTurnCollectionMother::fromList([
            AgentTurnMother::user('List everybody.'),
            AgentTurnMother::assistant($answer),
            AgentTurnMother::user('Thanks.'),
        ]));

        self::assertLessThan(AgentConversation::MAX_ASSISTANT_TURN_LENGTH, mb_strlen($answer), 'The limit must leave margin over the worst case.');
        self::assertSame($answer, $conversation->turns()->all()[1]->text());
    }

    public function testATurnOfTheAssistantOverItsLimitIsRejectedWithItsOwnLimit(): void
    {
        $this->expectException(InvalidAgentConversationException::class);
        $this->expectExceptionMessage('Turn 1 cannot be longer than 20000 characters.');

        AgentConversation::ofTurns(AgentTurnCollectionMother::fromList([
            AgentTurnMother::user('Hi'),
            AgentTurnMother::assistant(str_repeat('a', AgentConversation::MAX_ASSISTANT_TURN_LENGTH + 1)),
            AgentTurnMother::user('Thanks.'),
        ]));
    }

    /**
     * @throws InvalidAgentConversationException
     */
    public function testATurnOfTheAssistantOfExactlyItsLimitIsAccepted(): void
    {
        $text = str_repeat('a', AgentConversation::MAX_ASSISTANT_TURN_LENGTH);

        $conversation = AgentConversation::ofTurns(AgentTurnCollectionMother::fromList([
            AgentTurnMother::user('Hi'),
            AgentTurnMother::assistant($text),
            AgentTurnMother::user('Thanks.'),
        ]));

        self::assertSame($text, $conversation->turns()->all()[1]->text());
    }

    /**
     * The limit of the user does not move: a turn of the user over 2000 is still rejected, with its limit.
     */
    public function testTheLimitOfTheUserDoesNotChange(): void
    {
        self::assertSame(2000, AgentConversation::MAX_TURN_LENGTH);

        $this->expectException(InvalidAgentConversationException::class);
        $this->expectExceptionMessage('Turn 2 cannot be longer than 2000 characters.');

        AgentConversation::ofTurns(AgentTurnCollectionMother::fromList([
            AgentTurnMother::user('Hi'),
            AgentTurnMother::assistant('Hello.'),
            AgentTurnMother::user(str_repeat('a', AgentConversation::MAX_TURN_LENGTH + 1)),
        ]));
    }

    /**
     * The position is the one in the list the client sent, also when the window cut the start of it.
     */
    public function testThePositionOfALongTurnIsTheOneOfTheClientsListWhenTheWindowCutTheStart(): void
    {
        $turns = [];
        for ($i = 0; $i < AgentConversation::MAX_TURNS + 3; ++$i) {
            $turns[] = (0 === $i % 2 ? AgentTurnMother::user('turn ' . $i) : AgentTurnMother::assistant('turn ' . $i));
        }
        $turns[AgentConversation::MAX_TURNS + 2] = AgentTurnMother::user(str_repeat('a', AgentConversation::MAX_TURN_LENGTH + 1));

        $this->expectException(InvalidAgentConversationException::class);
        $this->expectExceptionMessage(\sprintf('Turn %d cannot be longer', AgentConversation::MAX_TURNS + 2));

        AgentConversation::ofTurns(AgentTurnCollectionMother::fromList($turns));
    }

    /**
     * It counts characters, not bytes: 2000 accented letters are 4000 bytes and a valid turn.
     *
     * @throws InvalidAgentConversationException
     */
    public function testCountsCharactersAndNotBytes(): void
    {
        $text = str_repeat('ñ', AgentConversation::MAX_TURN_LENGTH);

        self::assertSame([[AgentTurnRole::User, $text]], $this->asPairs(AgentConversation::ofQuestion($text)));
    }

    public function testTheErrorsDoNotRepeatWhatTheClientSaid(): void
    {
        $secret = 'secret-text';

        foreach ([
            [AgentTurnMother::user($secret), AgentTurnMother::assistant($secret)],
            [AgentTurnMother::user(str_repeat($secret, 300))],
        ] as $turns) {
            try {
                AgentConversation::ofTurns(AgentTurnCollectionMother::fromList($turns));
                self::fail('The conversation is invalid.');
            } catch (InvalidAgentConversationException $exception) {
                self::assertStringNotContainsString($secret, $exception->getMessage());
            }
        }
    }

    /**
     * It answers 400, not 422: the shape of the request is the caller's mistake.
     */
    public function testTheErrorIsABadRequest(): void
    {
        $this->expectException(BadRequestException::class);

        AgentConversation::ofTurns(AgentTurnCollectionMother::fromList([]));
    }

    /**
     * The turns are a typed collection, like the other ones of the project, and what the conversation
     * hands out is a copy: appending to it does not change the conversation.
     *
     * @throws InvalidAgentConversationException
     * @throws CollectionException
     */
    public function testTheTurnsAreATypedCollectionAndTheConversationDoesNotChangeWhenItIsAppendedTo(): void
    {
        // Arrange
        $conversation = AgentConversation::ofQuestion('Who is there?');

        // Act
        $turns = $conversation->turns();
        $turns->append(AgentTurn::of(AgentTurnRole::Assistant, 'An injected turn.'));

        // Assert
        self::assertInstanceOf(AgentTurnCollection::class, $turns);
        self::assertSame(AgentTurn::class, $turns->getTypeName());
        self::assertCount(2, $turns);
        self::assertCount(1, $conversation->turns(), 'The conversation must stay as it was validated.');
    }

    /**
     * @return list<array{AgentTurnRole, string}>
     */
    private function asPairs(AgentConversation $conversation): array
    {
        return array_values(array_map(static fn (AgentTurn $turn): array => [$turn->role(), $turn->text()], $conversation->turns()->all()));
    }
}
