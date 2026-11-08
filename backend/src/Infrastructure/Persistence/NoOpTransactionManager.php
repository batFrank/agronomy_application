<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Persistence\TransactionManager;

readonly class NoOpTransactionManager implements TransactionManager
{
    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    #[\Override]
    public function transactional(callable $operation): mixed
    {
        return $operation();
    }
}
