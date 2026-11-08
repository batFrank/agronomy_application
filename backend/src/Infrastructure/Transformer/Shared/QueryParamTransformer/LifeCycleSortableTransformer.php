<?php

declare(strict_types=1);

namespace App\Infrastructure\Transformer\Shared\QueryParamTransformer;

use App\Domain\Shared\Criteria\InvalidSortException;
use App\Domain\Shared\Criteria\SortableField;

class LifeCycleSortableTransformer extends SortableTransformer
{
    private const array SORTABLE_FIELDS = [
        'created_at' => 'createdAt',
        'updated_at' => 'updatedAt',
    ];

    public static function getSortableFields(): array
    {
        return self::SORTABLE_FIELDS;
    }

    public static function toSortableField(string $sortBy): SortableField
    {
        if (!\array_key_exists($sortBy, self::SORTABLE_FIELDS)) {
            throw InvalidSortException::invalidField($sortBy);
        }

        return new SortableField(self::SORTABLE_FIELDS[$sortBy]);
    }
}
