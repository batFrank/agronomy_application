<?php

declare(strict_types=1);

namespace App\Infrastructure\Bus;

use App\Domain\Bus\Command;
use App\Domain\Bus\CommandBus;
use App\Domain\Bus\Query;
use App\Domain\Bus\QueryBus;

abstract readonly class DuplexBusAccessor
{
    public function __construct(
        private QueryBus $queryBus,
        private CommandBus $commandBus,
    ) {
    }

    protected function ask(Query $query): mixed
    {
        return $this->queryBus->ask($query);
    }

    protected function execute(Command $command): mixed
    {
        return $this->commandBus->execute($command);
    }
}
