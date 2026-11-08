<?php

declare(strict_types=1);

namespace App\Infrastructure\Transformer\Shared\QueryParamTransformer;

use App\Domain\Shared\Criteria\InvalidSortException;
use App\Domain\Shared\Criteria\SortableField;
use App\Domain\Shared\Criteria\SortDirection;

abstract class SortableTransformer
{
    /**
     * @return array<string, string>
     */
    abstract public static function getSortableFields(): array;

    /**
     * @throws InvalidSortException
     */
    abstract public static function toSortableField(string $sortBy): SortableField;

    public static function toSortDirection(string $sortDirection): SortDirection
    {
        return match (strtolower($sortDirection)) {
            'desc' => SortDirection::DESC,
            default => SortDirection::ASC,
        };
    }
}
