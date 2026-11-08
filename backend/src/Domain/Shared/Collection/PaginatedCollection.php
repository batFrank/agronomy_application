<?php

declare(strict_types=1);

namespace App\Domain\Shared\Collection;

/**
 * @template T
 */
abstract class PaginatedCollection
{
    public function __construct(
        private readonly int $totalItems,
    ) {
    }

    /**
     * @return Collection<T>
     */
    abstract public function getCollection(): Collection;

    public function getTotalItems(): int
    {
        return $this->totalItems;
    }
}
