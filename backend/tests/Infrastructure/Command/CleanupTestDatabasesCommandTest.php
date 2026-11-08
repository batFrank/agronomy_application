<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Command;

use App\Infrastructure\Command\CleanupTestDatabasesCommand;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class CleanupTestDatabasesCommandTest extends TestCase
{
    private Connection&MockObject $connection;
    private CleanupTestDatabasesCommand $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);

        $this->command = new CleanupTestDatabasesCommand($this->connection);

        $this->commandTester = new CommandTester($this->command);
    }

    public function testExecuteWithNoDatabases(): void
    {
        $result = $this->createStub(Result::class);
        $result->method('fetchNumeric')->willReturn(false);

        $this->connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with(
                'SELECT datname FROM pg_database WHERE datname LIKE :pattern',
                $this->anything()
            )
            ->willReturn($result);

        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('No test databases found', $this->commandTester->getDisplay());
    }

    public function testExecuteWithDryRun(): void
    {
        $result = $this->createStub(Result::class);
        $result->method('fetchNumeric')
            ->willReturnOnConsecutiveCalls(
                ['edv_test_1'],
                ['edv_test_2'],
                false
            );

        $this->connection
            ->expects($this->once())
            ->method('executeQuery')
            ->willReturn($result);

        $this->connection
            ->expects($this->never())
            ->method('executeStatement');

        $exitCode = $this->commandTester->execute(['--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $display = $this->commandTester->getDisplay();
        $this->assertStringContainsString('DRY RUN', $display);
        $this->assertStringContainsString('edv_test_1', $display);
        $this->assertStringContainsString('edv_test_2', $display);
    }

    public function testExecuteWithForce(): void
    {
        $result = $this->createStub(Result::class);
        $result->method('fetchNumeric')
            ->willReturnOnConsecutiveCalls(
                ['edv_test_1'],
                ['edv_test_2'],
                false
            );

        $this->connection
            ->expects($this->once())
            ->method('executeQuery')
            ->willReturn($result);

        $this->connection
            ->expects($this->exactly(2))
            ->method('executeStatement')
            ->willReturnCallback(function (string $sql) {
                $this->assertStringContainsString('DROP DATABASE IF EXISTS', $sql);

                return 1;
            });

        $exitCode = $this->commandTester->execute(['--force' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $display = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Successfully deleted 2 test database(s)', $display);
    }

    public function testExecuteWithoutConfirmation(): void
    {
        $result = $this->createStub(Result::class);
        $result->method('fetchNumeric')
            ->willReturnOnConsecutiveCalls(
                ['edv_test_1'],
                false
            );

        $this->connection
            ->expects($this->once())
            ->method('executeQuery')
            ->willReturn($result);

        $this->connection
            ->expects($this->never())
            ->method('executeStatement');

        $this->commandTester->setInputs(['no']);
        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Operation cancelled', $this->commandTester->getDisplay());
    }

    public function testExecuteWithConfirmation(): void
    {
        $result = $this->createStub(Result::class);
        $result->method('fetchNumeric')
            ->willReturnOnConsecutiveCalls(
                ['edv_test_1'],
                false
            );

        $this->connection
            ->expects($this->once())
            ->method('executeQuery')
            ->willReturn($result);

        $this->connection
            ->expects($this->once())
            ->method('executeStatement')
            ->willReturn(1);

        $this->commandTester->setInputs(['yes']);
        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Successfully deleted 1 test database(s)', $this->commandTester->getDisplay());
    }

    public function testExecuteWithPartialFailure(): void
    {
        $result = $this->createStub(Result::class);
        $result->method('fetchNumeric')
            ->willReturnOnConsecutiveCalls(
                ['edv_test_1'],
                ['edv_test_2'],
                false
            );

        $this->connection
            ->expects($this->once())
            ->method('executeQuery')
            ->willReturn($result);

        $dbalException = $this->createStub(DeadlockException::class);

        $callCount = 0;
        $this->connection
            ->expects($this->exactly(2))
            ->method('executeStatement')
            ->willReturnCallback(function () use (&$callCount, $dbalException) {
                ++$callCount;
                if (2 === $callCount) {
                    throw $dbalException;
                }

                return 1;
            });

        $exitCode = $this->commandTester->execute(['--force' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $display = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Deleted 1 database(s), failed to delete 1', $display);
        $this->assertStringContainsString('Failed to delete', $display);
    }

    public function testExecuteWithConnectionError(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('executeQuery')
            ->willThrowException(new \Exception('Connection failed'));

        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('An error occurred', $this->commandTester->getDisplay());
    }
}
