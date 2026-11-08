<?php

declare(strict_types=1);

namespace App\Domain\Bus;

interface QueryBus
{
    public function ask(Query $query): mixed;
}
