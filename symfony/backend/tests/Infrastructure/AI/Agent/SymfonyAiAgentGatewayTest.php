<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Agent;

use App\Application\AI\AgentAnswer;
use App\Application\AI\AgentConversation;
use App\Application\AI\AgentUnavailableException;
use App\Application\AI\InvalidAgentConversationException;
use App\Application\Security\User as SecurityUser;
use App\Domain\Security\SecurityException;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Infrastructure\AI\Agent\SymfonyAiAgentGateway;
use App\Infrastructure\AI\Context\AgentContext;
use App\Tests\Mother\Application\AI\AgentTurnCollectionMother;
use App\Tests\Mother\Application\AI\AgentTurnMother;
use App\Tests\Mother\Application\Security\UserMother;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Exception\RuntimeException as AgentRuntimeException;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\Update\Result;
use Symfony\AI\Agent\Execution\UpdateInterface;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Message\Role;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\HttpClient\Exception\ClientException as HttpClientErrorException;
use Symfony\Component\HttpClient\Exception\EventSourceException as HttpEventSourceException;
use Symfony\Component\HttpClient\Exception\JsonException as HttpJsonException;
use Symfony\Component\HttpClient\Exception\RedirectionException as HttpRedirectionException;
use Symfony\Component\HttpClient\Exception\ServerException as HttpServerErrorException;
use Symfony\Component\HttpClient\Exception\TimeoutException as HttpTimeoutException;
use Symfony\Component\HttpClient\Exception\TransportException as HttpTransportException;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The agent's execution is lazy: AgentInterface::call() runs nothing, the tools run when the content
 * is read. These tests use a lazy execution that records what happens when it is consumed, so they
 * fail if the gateway ever releases the actor before the answer is resolved.
 */
#[CoversClass(SymfonyAiAgentGateway::class)]
class SymfonyAiAgentGatewayTest extends TestCase
{
    private AgentContext $context;

    protected function setUp(): void
    {
        $this->context = new AgentContext();
    }

    /**
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     * @throws AgentUnavailableException
     */
    public function testTheActorIsAvailableWhileTheLazyExecutionIsConsumed(): void
    {
        // Arrange
        $actor = UserMother::create();
        $seenWhileRunning = null;
        $agent = $this->agentReturning(function () use (&$seenWhileRunning): \Generator {
            // This is where the tools run: it only happens when the content is read.
            $seenWhileRunning = $this->actorOrNull();

            yield new Result(new TextResult('There are 3 users.'));
        });

        // Act
        $answer = (new SymfonyAiAgentGateway($agent, $this->context))->ask($actor, AgentConversation::ofQuestion('How many users?'));

        // Assert
        self::assertSame($actor, $seenWhileRunning, 'The tools must find the actor while the agent runs.');
        self::assertInstanceOf(AgentAnswer::class, $answer);
        self::assertSame('There are 3 users.', $answer->text());
    }

    /**
     * Fixes the requirement of AgentGateway: the answer comes back resolved. A gateway that returned
     * the lazy execution, or read it after leaving the context, would not have run the agent yet.
     *
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     * @throws AgentUnavailableException
     */
    public function testTheAnswerIsResolvedBeforeAskReturns(): void
    {
        // Arrange
        $consumed = false;
        $agent = $this->agentReturning(function () use (&$consumed): \Generator {
            $consumed = true;

            yield new Result(new TextResult('Done.'));
        });

        // Act
        (new SymfonyAiAgentGateway($agent, $this->context))->ask(UserMother::create(), AgentConversation::ofQuestion('Hi'));

        // Assert
        self::assertTrue($consumed, 'The execution must have run by the time ask() returns.');
    }

    /**
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     * @throws AgentUnavailableException
     */
    public function testTheContextIsLeftWhenTheAnswerIsDone(): void
    {
        // Arrange
        $agent = $this->agentReturning(static function (): \Generator {
            yield new Result(new TextResult('Done.'));
        });

        // Act
        (new SymfonyAiAgentGateway($agent, $this->context))->ask(UserMother::create(), AgentConversation::ofQuestion('Hi'));

        // Assert
        self::assertNull($this->actorOrNull());
    }

    /**
     * @param \Throwable $failure what the provider or the agent loop throws while running
     *
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    #[DataProvider('providerFailureProvider')]
    public function testAProviderFailureIsReportedAsAgentUnavailableAndTheContextIsLeft(\Throwable $failure): void
    {
        // Arrange
        $agent = $this->agentReturning(static function () use ($failure): \Generator {
            yield from [];

            throw $failure;
        });

        // Act
        try {
            (new SymfonyAiAgentGateway($agent, $this->context))->ask(UserMother::create(), AgentConversation::ofQuestion('Hi'));
            self::fail('A provider failure must not be swallowed.');
        } catch (AgentUnavailableException $exception) {
            // Assert
            self::assertSame($failure, $exception->getPrevious());
        }

        self::assertNull($this->actorOrNull(), 'The context must be left even when the agent fails.');
    }

    /**
     * @return \Generator<string, array{\Throwable}>
     */
    public static function providerFailureProvider(): \Generator
    {
        yield 'provider rate limit' => [new RateLimitExceededException()];
        yield 'agent loop failure' => [new AgentRuntimeException('The loop failed.')];

        // The SDK does not wrap the HTTP client's failures: they reach the gateway as they are. One
        // per kind covered by the parent interface (seven interfaces, all of them real classes).
        yield 'http transport (DNS failure)' => [new HttpTransportException('Could not resolve host: api.example.test')];
        yield 'http timeout' => [new HttpTimeoutException('Idle timeout reached.')];
        yield 'http client error (4xx)' => [new HttpClientErrorException(new MockResponse('', ['http_code' => 400]))];
        yield 'http server error (5xx)' => [new HttpServerErrorException(new MockResponse('', ['http_code' => 503]))];
        yield 'http redirection' => [new HttpRedirectionException(new MockResponse('', ['http_code' => 302]))];
        yield 'http decoding (invalid JSON)' => [new HttpJsonException('Syntax error')];
        yield 'http event source (decoding)' => [new HttpEventSourceException('Invalid event stream.')];
    }

    /**
     * The turns of the conversation reach the agent as the SDK's messages, in order and with their
     * roles. No system message: the agent adds its own prompt and the conversation cannot carry one.
     *
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     * @throws InvalidAgentConversationException
     * @throws AgentUnavailableException
     */
    public function testTheTurnsReachTheAgentAsMessagesInOrderWithTheirRoles(): void
    {
        // Arrange
        $agent = new class implements AgentInterface {
            public ?MessageBag $sent = null;

            public function call(string|MessageBag|UserMessage $input, array $options = []): Execution
            {
                $this->sent = $input instanceof MessageBag ? $input : null;

                return new Execution(static function (): \Generator {
                    yield new Result(new TextResult('Done.'));
                });
            }

            public function getName(): string
            {
                return 'recording-double';
            }
        };
        $conversation = AgentConversation::ofTurns(AgentTurnCollectionMother::fromList([
            AgentTurnMother::user('Who is there?'),
            AgentTurnMother::assistant('Three people.'),
            AgentTurnMother::user('And their roles?'),
        ]));

        // Act
        (new SymfonyAiAgentGateway($agent, $this->context))->ask(UserMother::create(), $conversation);

        // Assert
        $sent = $agent->sent;
        self::assertInstanceOf(MessageBag::class, $sent);
        self::assertSame(
            [[Role::User, 'Who is there?'], [Role::Assistant, 'Three people.'], [Role::User, 'And their roles?']],
            array_map(static fn (MessageInterface $message): array => [$message->getRole(), $message instanceof UserMessage || $message instanceof AssistantMessage ? $message->asText() : null], $sent->getMessages()),
        );
    }

    /**
     * The failure can also happen when the call starts, before there is an execution to read, and not
     * only while the content is being read. They are two different paths of the gateway.
     *
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    public function testAFailureWhileStartingTheCallIsReportedToo(): void
    {
        // Arrange
        $failure = new HttpTransportException('Could not resolve host: api.example.test');
        $agent = new class($failure) implements AgentInterface {
            public function __construct(private readonly \Throwable $failure)
            {
            }

            public function call(string|MessageBag|UserMessage $input, array $options = []): Execution
            {
                throw $this->failure;
            }

            public function getName(): string
            {
                return 'failing-double';
            }
        };

        // Act / Assert
        try {
            (new SymfonyAiAgentGateway($agent, $this->context))->ask(UserMother::create(), AgentConversation::ofQuestion('Hi'));
            self::fail('A transport failure must not be swallowed.');
        } catch (AgentUnavailableException $exception) {
            self::assertSame($failure, $exception->getPrevious());
        }

        self::assertNull($this->actorOrNull());
    }

    /**
     * A body cut half way: the first part arrives and then the transport fails.
     *
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    public function testAFailureWhileReadingTheBodyIsReportedToo(): void
    {
        // Arrange
        $failure = new HttpTransportException('Connection reset while reading the body.');
        $agent = $this->agentReturning(static function () use ($failure): \Generator {
            yield new Progress('request', 'The request was sent.');

            throw $failure;
        });

        // Act / Assert
        try {
            (new SymfonyAiAgentGateway($agent, $this->context))->ask(UserMother::create(), AgentConversation::ofQuestion('Hi'));
            self::fail('A transport failure must not be swallowed.');
        } catch (AgentUnavailableException $exception) {
            self::assertSame($failure, $exception->getPrevious());
        }

        self::assertNull($this->actorOrNull());
    }

    /**
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     * @throws AgentUnavailableException
     */
    public function testAnUnexpectedErrorIsNotWrappedButTheContextIsStillLeft(): void
    {
        // Arrange
        $agent = $this->agentReturning(static function (): \Generator {
            yield from [];

            throw new \DomainException('A bug, not a provider failure.');
        });

        // Act / Assert
        try {
            (new SymfonyAiAgentGateway($agent, $this->context))->ask(UserMother::create(), AgentConversation::ofQuestion('Hi'));
            self::fail('The error must propagate.');
        } catch (\DomainException) {
            self::assertNull($this->actorOrNull());
        }
    }

    /**
     * The second route of the adapter: the agent answered, but not with text. What the client sees is
     * generic; what was received stays in `previous`, for the log.
     *
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    public function testAnAnswerThatIsNotTextIsReportedAsAgentUnavailableInsteadOfBeingCoerced(): void
    {
        // Arrange
        $agent = $this->agentReturning(static function (): \Generator {
            yield new Result(self::resultWithObjectContent());
        });

        // Act / Assert
        try {
            (new SymfonyAiAgentGateway($agent, $this->context))->ask(UserMother::create(), AgentConversation::ofQuestion('Hi'));
            self::fail('An answer that is not text must not be coerced.');
        } catch (AgentUnavailableException $exception) {
            self::assertSame('The assistant did not give a usable answer.', $exception->getMessage());
            self::assertInstanceOf(\UnexpectedValueException::class, $exception->getPrevious());
            self::assertStringContainsString('stdClass', $exception->getPrevious()->getMessage());
            self::assertStringNotContainsString('stdClass', $exception->getMessage());
        }

        self::assertNull($this->actorOrNull(), 'The context must be left on this route too.');
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function emptyAnswerProvider(): \Generator
    {
        yield 'empty string' => [''];
        yield 'only spaces' => ['   '];
        yield 'only whitespace of every kind' => ["\t \n\r "];
    }

    /**
     * The criterion of the issue: an empty answer is a failure too, not a success with nothing in it.
     * It takes the same route as an answer that is not text, with the same message for the client, and
     * its cause tells the log that it was empty.
     *
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    #[DataProvider('emptyAnswerProvider')]
    public function testAnEmptyAnswerIsReportedAsAgentUnavailableAndNotReturnedAsSuccess(string $empty): void
    {
        // Arrange
        $agent = $this->agentReturning(static function () use ($empty): \Generator {
            yield new Result(new TextResult($empty));
        });

        // Act / Assert
        try {
            (new SymfonyAiAgentGateway($agent, $this->context))->ask(UserMother::create(), AgentConversation::ofQuestion('Hi'));
            self::fail('An empty answer must not be returned as a success.');
        } catch (AgentUnavailableException $exception) {
            self::assertSame('The assistant did not give a usable answer.', $exception->getMessage());
            self::assertInstanceOf(\UnexpectedValueException::class, $exception->getPrevious());
            self::assertSame('The agent answered with an empty text.', $exception->getPrevious()->getMessage());
        }

        self::assertNull($this->actorOrNull(), 'The context must be left on this route too.');
    }

    /**
     * The guard rejects what is empty, not what is short: a one character answer is an answer.
     *
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     * @throws AgentUnavailableException
     */
    public function testAnAnswerWithAnyContentIsStillASuccess(): void
    {
        // Arrange
        $agent = $this->agentReturning(static function (): \Generator {
            yield new Result(new TextResult('0'));
        });

        // Act
        $answer = (new SymfonyAiAgentGateway($agent, $this->context))->ask(UserMother::create(), AgentConversation::ofQuestion('Hi'));

        // Assert
        self::assertSame('0', $answer->text());
    }

    /**
     * What the client sees never repeats the provider: the message is the generic one, whatever the
     * failure was, and the failure travels in `previous`.
     *
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    public function testWhatTheClientSeesIsGenericAndTheProviderTextStaysInPrevious(): void
    {
        // Arrange
        $failure = new HttpTransportException('Could not resolve host: api.provider-secret.test (Authorization: Bearer sk-leak)');
        $agent = $this->agentReturning(static function () use ($failure): \Generator {
            yield from [];

            throw $failure;
        });

        // Act / Assert
        try {
            (new SymfonyAiAgentGateway($agent, $this->context))->ask(UserMother::create(), AgentConversation::ofQuestion('Hi'));
            self::fail('The failure must not be swallowed.');
        } catch (AgentUnavailableException $exception) {
            self::assertSame('The assistant is not available at the moment.', $exception->getMessage());
            self::assertStringNotContainsString('provider-secret', $exception->getMessage());
            self::assertStringNotContainsString('sk-leak', $exception->getMessage());
            self::assertSame($failure, $exception->getPrevious());
        }
    }

    /**
     * The context refuses a second actor. The call that is already running must keep its actor: the
     * refusal must not run the leave() of the gateway.
     *
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     * @throws SecurityException
     * @throws AgentUnavailableException
     */
    public function testANestedCallFailsAndDoesNotWipeTheActorOfTheRunningOne(): void
    {
        // Arrange
        $outer = UserMother::create();
        $this->context->enterAs($outer);
        $agent = $this->agentReturning(static function (): \Generator {
            yield new Result(new TextResult('Never reached.'));
        });

        // Act / Assert
        try {
            (new SymfonyAiAgentGateway($agent, $this->context))->ask(UserMother::create(), AgentConversation::ofQuestion('Hi'));
            self::fail('A second call while one is running must fail.');
        } catch (\LogicException) {
            self::assertSame($outer, $this->actorOrNull(), 'The running call must keep its actor.');
        } finally {
            $this->context->leave();
        }
    }

    /**
     * @param \Closure(): \Generator<int, UpdateInterface, mixed, void> $factory
     */
    private function agentReturning(\Closure $factory): AgentInterface
    {
        return new class($factory) implements AgentInterface {
            /** @param \Closure(): \Generator<int, UpdateInterface, mixed, void> $factory */
            public function __construct(private readonly \Closure $factory)
            {
            }

            public function call(string|MessageBag|UserMessage $input, array $options = []): Execution
            {
                return new Execution($this->factory);
            }

            public function getName(): string
            {
                return 'lazy-double';
            }
        };
    }

    private function actorOrNull(): ?SecurityUser
    {
        try {
            return $this->context->actor();
        } catch (\LogicException) {
            return null;
        }
    }

    private static function resultWithObjectContent(): ResultInterface
    {
        return new \Symfony\AI\Platform\Result\ObjectResult((object) ['not' => 'text']);
    }
}
