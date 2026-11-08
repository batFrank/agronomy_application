<?php

declare(strict_types=1);

namespace App\Infrastructure\Bus;

use App\Domain\Bus\Command;
use League\Tactician\CommandBus;

readonly class TacticianCommandBus implements \App\Domain\Bus\CommandBus
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    public function execute(Command $command): mixed
    {
        return $this->commandBus->handle($command);
    }
}
