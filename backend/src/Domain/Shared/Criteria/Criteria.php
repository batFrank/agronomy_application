<?php

declare(strict_types=1);

namespace App\Domain\Shared\Criteria;

/**
 * @template TCriteria
 */
abstract class Criteria
{
    private ?Pagination $pagination = null;
    private ?SortCollection $sortBy = null;

    final public function __construct()
    {
    }

    /**
     * @return static<TCriteria>
     */
    public static function createEmpty(): static
    {
        return new static();
    }

    public function withPagination(Pagination $pagination): static
    {
        $this->pagination = $pagination;

        return $this;
    }

    public function getPagination(): ?Pagination
    {
        return $this->pagination;
    }

    public function sortBy(SortCollection $sortBy): static
    {
        $this->sortBy = $sortBy;

        return $this;
    }

    public function getSortBy(): ?SortCollection
    {
        return $this->sortBy;
    }
}
