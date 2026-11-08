<?php

declare(strict_types=1);

namespace App\Domain\Shared\Criteria;

class Sort
{
    private SortableField $field;
    private SortDirection $direction;

    public function __construct(SortableField $field, SortDirection $direction)
    {
        $this->field = $field;
        $this->direction = $direction;
    }

    public function getField(): SortableField
    {
        return $this->field;
    }

    public function getDirection(): SortDirection
    {
        return $this->direction;
    }
}
