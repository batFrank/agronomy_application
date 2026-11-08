<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Agent;

use App\Application\AI\AgentAnswer;
use App\Application\AI\AgentConversation;
use App\Application\AI\AgentUnavailableException;
use App\Application\AI\InvalidAgentConversationException;
use App\Application\Query\AskAgent;
use App\Application\Security\User as SecurityUser;
use App\Domain\Bus\QueryBus;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\User\User;
use App\Infrastructure\AI\Context\AgentContext;
use App\Tests\Infrastructure\AI\Agent\Double\MessengerStyleAskAgentHandler;
use App\Tests\Infrastructure\AI\Agent\Double\ScriptedPlatform;
use App\Tests\Infrastructure\AI\Agent\UserDirectory\UserDirectoryScenarioTrait;
use App\Tests\Mother\Application\AI\AgentTurnCollectionMother;
use App\Tests\Mother\Application\AI\AgentTurnMother;
use PHPUnit\Framework\Assert;
use Symfony\AI\Agent\Exception\MaxIterationsExceededException;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Message\Role;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\Exception\TransportException as HttpTransportException;

/**
 * The whole path of the port, the way the application uses it: query bus, handler, gateway, the
 * `user_directory` agent from ai.yaml with its real tool and toolbox, and a scripted model. Nobody
 * in the test fills in the AgentContext: the gateway does.
 */
class AskAgentThroughTheBusTest extends KernelTestCase
{
    use UserDirectoryScenarioTrait;

    /**
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    protected function setUp(): void
    {
        self::bootKernel();
        $this->givenTheDirectory();
    }

    public function testAskingThroughTheBusRunsTheToolOnBehalfOfTheActor(): void
    {
        // Arrange
        $platform = $this->scriptedModelThatListsTheUsers();

        // Act
        $answer = $this->queryBus()->ask(new AskAgent($this->securityUserOf($this->manager), AgentConversation::ofQuestion('Who is there?')));

        // Assert
        self::assertInstanceOf(AgentAnswer::class, $answer);
        self::assertSame('Listed.', $answer->text());
        $received = $this->toolResultTheModelReceived($platform);
        self::assertStringContainsString('member@example.com', $received, 'The tool ran as the manager, so it lists their users.');
        self::assertStringNotContainsString('admin@example.com', $received);
        foreach ($this->everyIdentifier() as $identifier) {
            self::assertStringNotContainsString($identifier, $received);
        }
    }

    public function testTheContextIsEmptyAgainAfterAsking(): void
    {
        // Arrange
        $this->scriptedModelThatListsTheUsers();

        // Act
        $this->queryBus()->ask(new AskAgent($this->securityUserOf($this->admin), AgentConversation::ofQuestion('Who is there?')));

        // Assert
        $context = self::getContainer()->get('test.agent_context');
        Assert::assertInstanceOf(AgentContext::class, $context);
        $this->expectException(\LogicException::class);
        $context->actor();
    }

    public function testTheSameCallWorksFromAHandlerThatOnlyKnowsTheQueryBus(): void
    {
        // Arrange
        $this->scriptedModelThatListsTheUsers();
        $handler = new MessengerStyleAskAgentHandler($this->queryBus());

        // Act
        $text = $handler($this->securityUserOf($this->admin), 'Who is there?');

        // Assert
        self::assertSame('Listed.', $text);
    }

    /**
     * The real agent loop of the SDK does not wrap the HTTP client's failures: a transport failure
     * when the model is called reaches the gateway as it is, and the port must still keep its
     * contract. The scripted model fails the way the provider's client would.
     */
    public function testATransportFailureOfTheProviderReachesTheCallerAsAgentUnavailable(): void
    {
        // Arrange
        $failure = new HttpTransportException('Could not resolve host: openrouter.ai');
        $platform = self::getContainer()->get('test.ai.scripted_platform');
        Assert::assertInstanceOf(ScriptedPlatform::class, $platform);
        $platform->script(static function () use ($failure): never {
            throw $failure;
        });

        // Act / Assert
        try {
            $this->queryBus()->ask(new AskAgent($this->securityUserOf($this->admin), AgentConversation::ofQuestion('Who is there?')));
            self::fail('A transport failure must not be swallowed.');
        } catch (AgentUnavailableException $exception) {
            self::assertSame($failure, $exception->getPrevious());
        }

        $context = self::getContainer()->get('test.agent_context');
        Assert::assertInstanceOf(AgentContext::class, $context);
        $this->expectException(\LogicException::class);
        $context->actor();
    }

    /**
     * The conversation, end to end: the turns of the client reach the model in order and with their
     * roles, after the prompt of the agent, and the tool still runs for the actor of the call.
     */
    public function testTheTurnsOfTheConversationReachTheModelAfterThePrompt(): void
    {
        // Arrange
        $platform = $this->scriptedModelThatListsTheUsers();
        $conversation = AgentConversation::ofTurns(AgentTurnCollectionMother::fromList([
            AgentTurnMother::user('Hi'),
            AgentTurnMother::assistant('Hello, how can I help?'),
            AgentTurnMother::user('Who is there?'),
        ]));

        // Act
        $answer = $this->queryBus()->ask(new AskAgent($this->securityUserOf($this->manager), $conversation));

        // Assert
        self::assertInstanceOf(AgentAnswer::class, $answer);
        $firstCall = $platform->inputs()[0];
        self::assertInstanceOf(MessageBag::class, $firstCall);
        self::assertSame(
            [Role::System, Role::User, Role::Assistant, Role::User],
            array_map(static fn (MessageInterface $message): Role => $message->getRole(), $firstCall->getMessages()),
            'The only system message is the prompt of the agent: the client cannot send one.',
        );
        self::assertStringContainsString('member@example.com', $this->toolResultTheModelReceived($platform));
    }

    /**
     * The limit of rounds of tool calls (max_tool_calls in ai.yaml): a model that never stops asking
     * for the tool is cut, and what comes out is the same failure as any other of the agent.
     */
    public function testAModelThatNeverStopsCallingToolsIsCutAtTheConfiguredLimit(): void
    {
        // Arrange
        $platform = self::getContainer()->get('test.ai.scripted_platform');
        Assert::assertInstanceOf(ScriptedPlatform::class, $platform);
        $platform->script(static fn (): ToolCallResult => new ToolCallResult([new ToolCall('call-loop', 'list_users', [])]));

        // Act / Assert
        try {
            $this->queryBus()->ask(new AskAgent($this->securityUserOf($this->admin), AgentConversation::ofQuestion('Keep going.')));
            self::fail('A model that loops must be cut.');
        } catch (AgentUnavailableException $exception) {
            self::assertInstanceOf(MaxIterationsExceededException::class, $exception->getPrevious());
        }

        // The limit is 5 rounds: the call number 6 asks for the tool again and that is the one that throws.
        self::assertCount(6, $platform->inputs());
    }

    /**
     * An oversized question cannot even become a query, so it never reaches the provider, where it
     * would have come back as a failure of its own (a 500 for a mistake of the caller).
     */
    public function testAnOversizedQuestionNeverReachesTheProvider(): void
    {
        // Arrange
        $platform = self::getContainer()->get('test.ai.scripted_platform');
        Assert::assertInstanceOf(ScriptedPlatform::class, $platform);

        // Act / Assert
        try {
            $this->queryBus()->ask(new AskAgent(
                $this->securityUserOf($this->admin),
                AgentConversation::ofQuestion(str_repeat('a', AgentConversation::MAX_TURN_LENGTH + 1)),
            ));
            self::fail('An oversized question must be rejected.');
        } catch (InvalidAgentConversationException) {
            self::assertSame([], $platform->inputs());
        }
    }

    private function scriptedModelThatListsTheUsers(): ScriptedPlatform
    {
        $platform = self::getContainer()->get('test.ai.scripted_platform');
        Assert::assertInstanceOf(ScriptedPlatform::class, $platform);
        $platform->script(static function (array|string|object $input): ResultInterface|string {
            Assert::assertInstanceOf(MessageBag::class, $input);

            // It answers once the tool result is in the conversation, whatever the length of the history.
            $hasToolResult = [] !== array_filter($input->getMessages(), static fn (MessageInterface $message): bool => $message instanceof ToolCallMessage);

            return $hasToolResult
                ? 'Listed.'
                : new ToolCallResult([new ToolCall('call-1', 'list_users', [])]);
        });

        return $platform;
    }

    private function toolResultTheModelReceived(ScriptedPlatform $platform): string
    {
        $secondCall = $platform->inputs()[1] ?? null;
        Assert::assertInstanceOf(MessageBag::class, $secondCall);

        return implode("\n", array_map(
            static fn (object $message): string => $message instanceof ToolCallMessage ? (string) $message->asText() : '',
            $secondCall->getMessages(),
        ));
    }

    private function queryBus(): QueryBus
    {
        $bus = self::getContainer()->get(QueryBus::class);
        Assert::assertInstanceOf(QueryBus::class, $bus);

        return $bus;
    }

    private function securityUserOf(User $user): SecurityUser
    {
        return SecurityUser::createFromDomainUser($user);
    }
}
