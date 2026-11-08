<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Toolbox;

use App\Domain\Bus\Command;
use App\Domain\Bus\CommandBus;
use App\Domain\Bus\Query;
use App\Domain\Bus\QueryBus;
use App\Domain\Shared\Exception\NotFoundException;
use App\Infrastructure\AI\Context\AgentContext;
use App\Infrastructure\AI\Toolbox\AgentTool;
use App\Infrastructure\AI\Toolbox\ToolCallException;
use App\Infrastructure\AI\Toolbox\ToolExceptionMapper;
use App\Infrastructure\AI\Toolbox\Translator\NotFoundExceptionTranslator;
use App\Tests\Infrastructure\AI\Toolbox\Double\ProbeAgentTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AgentTool::class)]
class AgentToolTest extends TestCase
{
    public function testThrowsToolCallExceptionWithPreviousWhenQueryFails(): void
    {
        // Arrange
        $failure = new NotFoundException('Ticket 9 does not exist');
        $queryBus = $this->createStub(QueryBus::class);
        $queryBus->method('ask')->willThrowException($failure);
        $tool = $this->createTool($queryBus, $this->createStub(CommandBus::class));

        // Act
        $exception = $this->captureException(fn () => $tool->askQuery(new class implements Query {}));

        // Assert
        self::assertInstanceOf(ToolCallException::class, $exception);
        self::assertSame(
            'The requested resource was not found. Reason: Ticket 9 does not exist',
            $exception->getToolCallResult(),
        );
        self::assertSame($failure, $exception->getPrevious());
    }

    public function testThrowsToolCallExceptionWithPreviousWhenCommandFails(): void
    {
        // Arrange
        $failure = new NotFoundException('Ticket 9 does not exist');
        $commandBus = $this->createStub(CommandBus::class);
        $commandBus->method('execute')->willThrowException($failure);
        $tool = $this->createTool($this->createStub(QueryBus::class), $commandBus);

        // Act
        $exception = $this->captureException(fn () => $tool->executeCommand(new class implements Command {}));

        // Assert
        self::assertInstanceOf(ToolCallException::class, $exception);
        self::assertSame(
            'The requested resource was not found. Reason: Ticket 9 does not exist',
            $exception->getToolCallResult(),
        );
        self::assertSame($failure, $exception->getPrevious());
    }

    public function testReturnsQueryResultUnchanged(): void
    {
        // Arrange
        $queryBus = $this->createStub(QueryBus::class);
        $queryBus->method('ask')->willReturn('query-result');
        $tool = $this->createTool($queryBus, $this->createStub(CommandBus::class));

        // Act
        $result = $tool->askQuery(new class implements Query {});

        // Assert
        self::assertSame('query-result', $result);
    }

    public function testReturnsCommandResultUnchanged(): void
    {
        // Arrange
        $commandBus = $this->createStub(CommandBus::class);
        $commandBus->method('execute')->willReturn('command-result');
        $tool = $this->createTool($this->createStub(QueryBus::class), $commandBus);

        // Act
        $result = $tool->executeCommand(new class implements Command {});

        // Assert
        self::assertSame('command-result', $result);
    }

    private function createTool(QueryBus $queryBus, CommandBus $commandBus): ProbeAgentTool
    {
        return new ProbeAgentTool($queryBus, $commandBus, new AgentContext(), new ToolExceptionMapper([
            new NotFoundExceptionTranslator(),
        ]));
    }

    private function captureException(callable $call): \Throwable
    {
        try {
            $call();
        } catch (\Throwable $exception) {
            return $exception;
        }

        self::fail('Expected an exception to be thrown.');
    }
}
