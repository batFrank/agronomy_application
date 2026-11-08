<?php

declare(strict_types=1);

namespace App\Domain\Shared\Criteria;

class Pagination
{
    private int $page;
    private int $limit;

    public function __construct(int $page, int $limit)
    {
        $this->page = $page;
        $this->limit = $limit;
    }

    public function page(): int
    {
        return $this->page;
    }

    public function limit(): int
    {
        return $this->limit;
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->limit;
    }
}
