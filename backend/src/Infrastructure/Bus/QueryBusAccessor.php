<?php

declare(strict_types=1);

namespace App\Infrastructure\Bus;

use App\Domain\Bus\Query;
use App\Domain\Bus\QueryBus;

abstract readonly class QueryBusAccessor
{
    public function __construct(private QueryBus $queryBus)
    {
    }

    protected function ask(Query $query): mixed
    {
        return $this->queryBus->ask($query);
    }
}
