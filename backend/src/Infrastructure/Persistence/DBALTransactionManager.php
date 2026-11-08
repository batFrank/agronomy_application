<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Persistence\TransactionManager;
use App\Domain\Shared\Exception\InfrastructureException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;

readonly class DBALTransactionManager implements TransactionManager
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     *
     * @throws InfrastructureException
     * @throws DBALException
     * @throws \Throwable
     */
    #[\Override]
    public function transactional(callable $operation): mixed
    {
        try {
            $this->connection->beginTransaction();
            $result = $operation();
            $this->connection->commit();

            return $result;
        } catch (DBALException $e) {
            $this->connection->rollBack();
            throw InfrastructureException::fromPrevious($e);
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }
}
