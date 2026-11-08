<?php

declare(strict_types=1);

namespace App\Infrastructure\Bus;

use App\Domain\Bus\Query;
use App\Domain\Bus\QueryBus;
use League\Tactician\CommandBus;

readonly class TacticianQueryBus implements QueryBus
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    public function ask(Query $query): mixed
    {
        return $this->commandBus->handle($query);
    }
}
