<?php

declare(strict_types=1);

namespace App\Application\Persistence;

use App\Domain\Shared\Exception\InfrastructureException;

interface TransactionManager
{
    /**
     * Executes $operation inside a database transaction.
     * On success, commits and returns the value returned by $operation.
     * On any exception, rolls back and re-throws the original exception.
     *
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     *
     * @throws InfrastructureException
     */
    public function transactional(callable $operation): mixed;
}
