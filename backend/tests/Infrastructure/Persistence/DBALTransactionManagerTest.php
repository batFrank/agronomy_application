<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Persistence;

use App\Domain\Shared\Exception\InfrastructureException;
use App\Infrastructure\Persistence\DBALTransactionManager;
use App\Tests\Infrastructure\Persistence\DBAL\DBALConnectionFactory;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

class DBALTransactionManagerTest extends TestCase
{
    private function getTransactionManager(?Connection $connection = null): DBALTransactionManager
    {
        return new DBALTransactionManager(
            $connection ?? $this->createMock(Connection::class),
        );
    }

    /**
     * @throws DBALException
     * @throws Exception
     * @throws InfrastructureException
     * @throws \Throwable
     */
    public function testTransactionalWrapsAndThrowsInfrastructureExceptionOnDBALException(): void
    {
        $this->expectException(InfrastructureException::class);

        $dbalException = $this->createStub(DBALException::class);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->once())->method('rollBack');
        $connection->expects($this->never())->method('commit');

        $transactionManager = $this->getTransactionManager($connection);
        $transactionManager->transactional(function () use ($dbalException): void {
            throw $dbalException;
        });
    }

    /**
     * @throws DBALException
     * @throws InfrastructureException
     * @throws \Throwable
     */
    public function testTransactionalRethrowsOriginalExceptionAndRollsBackOnGenericThrowable(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Simulated domain failure');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->once())->method('rollBack');
        $connection->expects($this->never())->method('commit');

        $transactionManager = $this->getTransactionManager($connection);
        $transactionManager->transactional(function (): void {
            throw new \RuntimeException('Simulated domain failure');
        });
    }

    /**
     * @throws DBALException
     * @throws InfrastructureException
     * @throws \Throwable
     */
    public function testTransactionalCommitsAndReturnsValueOnSuccess(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->once())->method('commit');
        $connection->expects($this->never())->method('rollBack');

        $transactionManager = $this->getTransactionManager($connection);
        $result = $transactionManager->transactional(fn (): string => 'ok');

        $this->assertSame('ok', $result);
    }

    /**
     * @throws DBALException
     * @throws InfrastructureException
     * @throws \Throwable
     */
    public function testTransactionalCommitsAndReturnsValueOnSuccessWithRealConnection(): void
    {
        [$connection] = DBALConnectionFactory::createNewConnection();
        $manager = new DBALTransactionManager($connection);

        $result = $manager->transactional(fn (): int => 42);

        $this->assertSame(42, $result);
        $this->assertFalse($connection->isTransactionActive());

        $connection->close();
    }

    /**
     * @throws DBALException
     * @throws InfrastructureException
     * @throws \Throwable
     */
    public function testTransactionalWithRealConnectionRollsBackOnFailure(): void
    {
        [$connection] = DBALConnectionFactory::createNewConnection();
        $manager = new DBALTransactionManager($connection);

        $this->expectException(\LogicException::class);

        try {
            $manager->transactional(function (): void {
                throw new \LogicException('Rollback trigger');
            });
        } finally {
            $this->assertFalse($connection->isTransactionActive());
            $connection->close();
        }
    }
}
