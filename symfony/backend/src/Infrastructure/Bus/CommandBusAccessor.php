<?php

declare(strict_types=1);

namespace App\Infrastructure\Bus;

use App\Domain\Bus\Command;
use App\Domain\Bus\CommandBus;

abstract readonly class CommandBusAccessor
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    protected function execute(Command $command): mixed
    {
        return $this->commandBus->execute($command);
    }
}
