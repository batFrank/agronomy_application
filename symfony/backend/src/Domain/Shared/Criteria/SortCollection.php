<?php

declare(strict_types=1);

namespace App\Domain\Shared\Criteria;

use App\Domain\Shared\Collection\Collection;

/**
 * @extends Collection<Sort>
 */
class SortCollection extends Collection
{
    public function getTypeName(): string
    {
        return Sort::class;
    }
}
