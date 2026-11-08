<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Agent\UserDirectory;

use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Tests\Infrastructure\AI\Agent\Double\ScriptedPlatform;
use PHPUnit\Framework\Assert;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The whole loop of the `user_directory` agent built from ai.yaml, with a scripted model instead of
 * the provider: the model asks for the tool, the toolbox runs it, and the next call carries the tool
 * result. What that second call carries is what the model receives.
 */
class UserDirectoryAgentTest extends KernelTestCase
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

    protected function tearDown(): void
    {
        $this->leaveTheContext();

        parent::tearDown();
    }

    public function testTheModelNeverReceivesAnIdentifierAndTheSystemPromptComesFromTheFile(): void
    {
        // Arrange
        $this->givenTheActorIs($this->admin);
        $platform = $this->platform();
        $platform->script(function (array|string|object $input): ResultInterface|string {
            Assert::assertInstanceOf(MessageBag::class, $input);

            return $input->count() > 2
                ? 'There are 3 users.'
                : new ToolCallResult([new ToolCall('call-1', 'list_users', [])]);
        });

        // Act
        $execution = $this->agent()->call('How many users are there?');

        // Assert
        self::assertSame('There are 3 users.', $execution->getContent());
        $inputs = $platform->inputs();
        self::assertCount(2, $inputs);

        $firstCall = $inputs[0];
        self::assertInstanceOf(MessageBag::class, $firstCall);
        self::assertStringContainsString('user directory assistant', (string) $firstCall->getSystemMessage()?->getContent());

        $secondCall = $inputs[1];
        self::assertInstanceOf(MessageBag::class, $secondCall);
        $toolMessages = array_values(array_filter(
            $secondCall->getMessages(),
            static fn (object $message): bool => $message instanceof ToolCallMessage,
        ));
        self::assertCount(1, $toolMessages);
        $receivedByTheModel = (string) $toolMessages[0]->asText();
        self::assertStringContainsString('admin@example.com', $receivedByTheModel);
        foreach ($this->everyIdentifier() as $identifier) {
            self::assertStringNotContainsString($identifier, $receivedByTheModel);
        }
    }

    public function testAManagerOnlyGetsTheirOwnUsersThroughTheWholeLoop(): void
    {
        // Arrange
        $this->givenTheActorIs($this->manager);
        $platform = $this->platform();
        $platform->script(function (array|string|object $input): ResultInterface|string {
            Assert::assertInstanceOf(MessageBag::class, $input);

            return $input->count() > 2
                ? 'Done.'
                : new ToolCallResult([new ToolCall('call-1', 'list_users', [])]);
        });

        // Act
        // The execution is lazy: reading the content is what runs the loop.
        $this->agent()->call('Who is there?')->getContent();

        // Assert
        $secondCall = $platform->inputs()[1];
        self::assertInstanceOf(MessageBag::class, $secondCall);
        $received = implode("\n", array_map(
            static fn (object $message): string => $message instanceof ToolCallMessage ? (string) $message->asText() : '',
            $secondCall->getMessages(),
        ));
        self::assertStringContainsString('member@example.com', $received);
        self::assertStringNotContainsString('admin@example.com', $received);
    }

    private function platform(): ScriptedPlatform
    {
        $platform = self::getContainer()->get('test.ai.scripted_platform');
        Assert::assertInstanceOf(ScriptedPlatform::class, $platform);

        return $platform;
    }

    private function agent(): AgentInterface
    {
        $agent = self::getContainer()->get('test.ai.agent.user_directory');
        Assert::assertInstanceOf(AgentInterface::class, $agent);

        return $agent;
    }
}
