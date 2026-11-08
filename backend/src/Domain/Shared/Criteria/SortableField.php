<?php

declare(strict_types=1);

namespace App\Domain\Shared\Criteria;

class SortableField
{
    private string $field;

    public function __construct(string $field)
    {
        $this->field = $field;
    }

    public function value(): string
    {
        return $this->field;
    }
}
