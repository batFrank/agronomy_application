<?php

declare(strict_types=1);

namespace App\Domain\Shared\Uuid;

use App\Domain\Shared\Collection\Collection;

/**
 * @extends Collection<Uuid>
 */
class UuidCollection extends Collection
{
    public function getTypeName(): string
    {
        return Uuid::class;
    }
}
