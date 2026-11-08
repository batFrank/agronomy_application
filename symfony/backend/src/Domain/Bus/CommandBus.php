<?php

declare(strict_types=1);

namespace App\Domain\Bus;

interface CommandBus
{
    public function execute(Command $command): mixed;
}
