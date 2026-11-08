<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Agent;

use App\Application\AI\AgentConversation;
use App\Application\AI\AgentUnavailableException;
use App\Application\AI\InvalidAgentConversationException;
use App\Application\Query\AskAgent;
use App\Application\Security\User as SecurityUser;
use App\Domain\Bus\QueryBus;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Infrastructure\AI\Context\AgentContext;
use App\Infrastructure\Listener\ExceptionListener;
use App\Infrastructure\Log\MonologLogger;
use App\Tests\Infrastructure\AI\Agent\Double\ScriptedPlatform;
use App\Tests\Infrastructure\AI\Agent\UserDirectory\UserDirectoryScenarioTrait;
use App\Tests\Mother\Application\AI\AgentTurnCollectionMother;
use App\Tests\Mother\Application\AI\AgentTurnMother;
use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * What a client would get when the provider fails, end to end: the real agent loop and the real
 * adapter raise the exception, and the real ExceptionListener and the real logger handle it. The
 * inheritance is not trusted: the mapping that counts is the one that happens here.
 */
class AgentUnavailableThroughTheListenerTest extends KernelTestCase
{
    use UserDirectoryScenarioTrait;

    private const string PROVIDER_TEXT = 'Could not resolve host: api.provider-secret.test (Authorization: Bearer sk-leak)';

    private string $logDirectory;

    /**
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    protected function setUp(): void
    {
        self::bootKernel();
        $this->givenTheDirectory();
        $this->logDirectory = sys_get_temp_dir() . '/agent-unavailable-' . bin2hex(random_bytes(6));
        (new Filesystem())->mkdir($this->logDirectory);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->logDirectory);

        parent::tearDown();
    }

    public function testTheClientGetsA502WithAGenericMessageAndTheLogGetsTheDetail(): void
    {
        // Arrange
        $failure = new TransportException(self::PROVIDER_TEXT);
        $platform = self::getContainer()->get('test.ai.scripted_platform');
        Assert::assertInstanceOf(ScriptedPlatform::class, $platform);
        $platform->script(static function () use ($failure): never {
            throw $failure;
        });
        $logger = new MonologLogger($this->logDirectory);

        // Act
        try {
            $this->queryBus()->ask(new AskAgent(SecurityUser::createFromDomainUser($this->admin), AgentConversation::ofQuestion('Who is there?')));
            self::fail('The provider failed: the question cannot be answered.');
        } catch (AgentUnavailableException $exception) {
            $response = $this->handledByTheRealListener($exception, $logger);
        }

        // Assert: what the client sees
        self::assertSame(Response::HTTP_BAD_GATEWAY, $response->getStatusCode());
        $body = (string) $response->getContent();
        self::assertStringContainsString('The assistant is not available at the moment.', $body);
        self::assertStringNotContainsString('provider-secret', $body);
        self::assertStringNotContainsString('sk-leak', $body);
        self::assertStringNotContainsString('TransportException', $body);

        // Assert: what the log keeps, through `previous`
        $log = implode("\n", $logger->getLogMessages());
        self::assertStringContainsString('The assistant is not available at the moment.', $log);
        self::assertStringContainsString('api.provider-secret.test', $log, 'The detail of the provider must reach the log.');
        self::assertStringContainsString('[previous exception]', $log);
        self::assertStringContainsString('TransportException', $log);
    }

    /**
     * The second route of the criterion of the issue, end to end: the model answers with nothing. The
     * real loop, adapter and bus, then the real listener and logger. There is no exception to wrap, so
     * the log keeps a cause that says what happened.
     */
    public function testAnEmptyAnswerGetsA502WithAGenericMessageAndTheLogSaysItWasEmpty(): void
    {
        // Arrange
        $platform = self::getContainer()->get('test.ai.scripted_platform');
        Assert::assertInstanceOf(ScriptedPlatform::class, $platform);
        $platform->script(static fn (): string => '   ');
        $logger = new MonologLogger($this->logDirectory);

        // Act
        try {
            $answer = $this->queryBus()->ask(new AskAgent(SecurityUser::createFromDomainUser($this->admin), AgentConversation::ofQuestion('Who is there?')));
            self::fail(\sprintf('An empty answer must not be a success, got: %s', var_export($answer, true)));
        } catch (AgentUnavailableException $exception) {
            $response = $this->handledByTheRealListener($exception, $logger);
        }

        // Assert: what the client sees
        self::assertSame(Response::HTTP_BAD_GATEWAY, $response->getStatusCode());
        $body = (string) $response->getContent();
        self::assertStringContainsString('The assistant did not give a usable answer.', $body);
        self::assertStringNotContainsString('empty text', $body);
        self::assertStringNotContainsString('UnexpectedValueException', $body);

        // Assert: what the log keeps, through the cause built for it
        $log = implode("\n", $logger->getLogMessages());
        self::assertStringContainsString('The assistant did not give a usable answer.', $log);
        self::assertStringContainsString('[previous exception]', $log);
        self::assertStringContainsString('The agent answered with an empty text.', $log);
    }

    /**
     * The conversation that is well formed but not valid, through the real listener: a 400 whose message
     * names the turn and not what it said, and that is not logged (a 400 never is).
     */
    public function testAnInvalidConversationGetsA400AndIsNotLogged(): void
    {
        // Arrange
        $logger = new MonologLogger($this->logDirectory);

        // Act
        try {
            AgentConversation::ofTurns(AgentTurnCollectionMother::fromList([
                AgentTurnMother::user('Hi'),
                AgentTurnMother::assistant(str_repeat('sentinel-secret-text', AgentConversation::MAX_ASSISTANT_TURN_LENGTH)),
                AgentTurnMother::user(str_repeat('sentinel-secret-text', AgentConversation::MAX_TURN_LENGTH)),
            ]));
            self::fail('A conversation with a turn that is too long must be rejected.');
        } catch (InvalidAgentConversationException $exception) {
            $response = $this->handledByTheRealListener($exception, $logger);
        }

        // Assert
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $body = (string) $response->getContent();
        self::assertStringContainsString('Turn 1 cannot be longer than 20000 characters.', $body);
        self::assertStringNotContainsString('sentinel-secret-text', $body);
        self::assertSame([], $logger->getLogMessages(), 'A 400 is not logged.');
    }

    public function testTheContextIsLeftOnThisPathToo(): void
    {
        // Arrange
        $platform = self::getContainer()->get('test.ai.scripted_platform');
        Assert::assertInstanceOf(ScriptedPlatform::class, $platform);
        $platform->script(static function (): never {
            throw new TransportException(self::PROVIDER_TEXT);
        });

        // Act
        try {
            $this->queryBus()->ask(new AskAgent(SecurityUser::createFromDomainUser($this->admin), AgentConversation::ofQuestion('Who is there?')));
            self::fail('The provider failed.');
        } catch (AgentUnavailableException) {
        }

        // Assert
        $context = self::getContainer()->get('test.agent_context');
        Assert::assertInstanceOf(AgentContext::class, $context);
        $this->expectException(\LogicException::class);
        $context->actor();
    }

    private function handledByTheRealListener(\Throwable $exception, MonologLogger $logger): Response
    {
        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );

        (new ExceptionListener($logger))->onKernelException($event);

        $response = $event->getResponse();
        Assert::assertInstanceOf(JsonResponse::class, $response);

        return $response;
    }

    private function queryBus(): QueryBus
    {
        $bus = self::getContainer()->get(QueryBus::class);
        Assert::assertInstanceOf(QueryBus::class, $bus);

        return $bus;
    }
}
