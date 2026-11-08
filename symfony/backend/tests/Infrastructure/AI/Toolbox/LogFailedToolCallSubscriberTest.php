<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Toolbox;

use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Exception\LoggableException;
use App\Domain\Shared\Exception\NotFoundException;
use App\Domain\User\Exception\UserForbiddenException;
use App\Infrastructure\AI\Toolbox\LogFailedToolCallSubscriber;
use App\Infrastructure\AI\Toolbox\ToolCallException;
use App\Infrastructure\Log\InMemoryLogger;
use App\Infrastructure\Validator\ValidatorException;
use App\Tests\Infrastructure\AI\Toolbox\Double\ProbeTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\AI\Agent\Toolbox\Event\ToolCallFailed;
use Symfony\AI\Agent\Toolbox\Exception\InvalidToolCallArgumentsException;
use Symfony\AI\Agent\Toolbox\Exception\ToolException;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[CoversClass(LogFailedToolCallSubscriber::class)]
class LogFailedToolCallSubscriberTest extends \PHPUnit\Framework\TestCase
{
    private const string SECRET_ARGUMENT_VALUE = 'valor-secreto';
    private const string NESTED_ARGUMENT_VALUE = 'valor-anidado';

    #[DataProvider('expectedFailureProvider')]
    public function testDoesNotLogExpectedFailures(\Throwable $failure): void
    {
        // Arrange
        $logger = new InMemoryLogger();
        $subscriber = new LogFailedToolCallSubscriber($logger);

        // Act
        $subscriber->onToolCallFailed($this->createEvent($failure));

        // Assert
        self::assertSame([], $logger->getLogMessages());
    }

    public static function expectedFailureProvider(): \Generator
    {
        yield 'not found wrapped in tool call exception' => [
            ToolCallException::withReason('The requested resource was not found.', new NotFoundException('Product 42')),
        ];

        yield 'forbidden wrapped in tool call exception' => [
            ToolCallException::withReason('The current user is not allowed to perform this operation.', UserForbiddenException::cannotViewUser()),
        ];

        yield 'validator wrapped in tool call exception' => [
            ToolCallException::withReason('The input is not valid.', ValidatorException::createByMessage('Invalid city')),
        ];

        yield 'invalid tool call arguments' => [
            new InvalidToolCallArgumentsException(),
        ];

        yield 'tool exception' => [
            ToolException::invalidReference('probe'),
        ];

        yield 'access denied' => [
            new AccessDeniedException(),
        ];

        yield 'reason thrown on purpose without previous' => [
            ToolCallException::withReason('The tool refused the request.'),
        ];
    }

    #[DataProvider('unexpectedFailureProvider')]
    public function testLogsUnexpectedFailureCauseWithArgumentKeysOnly(\Throwable $failure, \Throwable $expectedCause): void
    {
        // Arrange
        $logger = new InMemoryLogger();
        $subscriber = new LogFailedToolCallSubscriber($logger);

        // Act
        $subscriber->onToolCallFailed($this->createEvent($failure));

        // Assert
        $logMessages = $logger->getLogMessages();
        self::assertCount(1, $logMessages);
        self::assertEquals(
            [
                'message' => 'Tool "probe" failed.',
                'exception' => $expectedCause,
                'tool' => 'probe',
                'arguments' => ['city'],
            ],
            $logMessages[0],
        );
        self::assertSame($expectedCause, $logMessages[0]['exception']);
        self::assertStringNotContainsString(self::SECRET_ARGUMENT_VALUE, (string) $logMessages[0]['message']);
    }

    public static function unexpectedFailureProvider(): \Generator
    {
        $databaseFailure = new \RuntimeException('Connection refused');
        yield 'unexpected error inside the bus' => [
            ToolCallException::unexpected($databaseFailure),
            $databaseFailure,
        ];

        $logicFailure = new \LogicException('Handler without a return value');
        yield 'raw logic exception' => [
            $logicFailure,
            $logicFailure,
        ];

        $invalidEmail = new InvalidEmailException('not-an-email');
        yield 'unprocessable entity wrapped in tool call exception' => [
            ToolCallException::withReason('The operation was rejected by a business rule.', $invalidEmail),
            $invalidEmail,
        ];

        $loggableNotFound = new class('Loggable not found') extends NotFoundException implements LoggableException {
        };
        yield 'loggable exception even when it extends an expected one' => [
            ToolCallException::withReason('The requested resource was not found.', $loggableNotFound),
            $loggableNotFound,
        ];
    }

    public function testDoesNotDumpTheArgumentPayloadAnywhereInTheLogRecord(): void
    {
        // Arrange
        $logger = new InMemoryLogger();
        $subscriber = new LogFailedToolCallSubscriber($logger);
        $failure = ToolCallException::unexpected(new \RuntimeException('Connection refused'));
        $arguments = [
            'city' => self::SECRET_ARGUMENT_VALUE,
            'filters' => ['zip' => self::NESTED_ARGUMENT_VALUE],
        ];

        // Act
        $subscriber->onToolCallFailed($this->createEvent($failure, $arguments));

        // Assert
        $logMessages = $logger->getLogMessages();
        self::assertCount(1, $logMessages);
        $record = $this->renderRecord($logMessages[0]);
        self::assertStringNotContainsString(self::SECRET_ARGUMENT_VALUE, $record);
        self::assertStringNotContainsString(self::NESTED_ARGUMENT_VALUE, $record);
        self::assertSame(['city', 'filters'], $logMessages[0]['arguments']);
    }

    /**
     * Renders every field of the record, the cause chain included, the way a handler would write it.
     *
     * @param array<string, mixed> $record
     */
    private function renderRecord(array $record): string
    {
        $cause = $record['exception'] ?? null;
        unset($record['exception']);

        $rendered = json_encode($record, JSON_THROW_ON_ERROR);
        for ($exception = $cause; $exception instanceof \Throwable; $exception = $exception->getPrevious()) {
            $rendered .= \sprintf(' %s: %s', $exception::class, $exception->getMessage());
        }

        return $rendered;
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function createEvent(\Throwable $failure, array $arguments = ['city' => self::SECRET_ARGUMENT_VALUE]): ToolCallFailed
    {
        return new ToolCallFailed(
            new \stdClass(),
            new Tool(new ExecutionReference(ProbeTool::class, '__invoke'), 'probe', 'Probe tool.', null),
            $arguments,
            $failure,
        );
    }
}
