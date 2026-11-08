<?php

declare(strict_types=1);

namespace App\Infrastructure\Transformer\User;

use App\Domain\Shared\Criteria\InvalidSortException;
use App\Domain\Shared\Criteria\SortableField;
use App\Infrastructure\Transformer\Shared\QueryParamTransformer\LifeCycleSortableTransformer;
use App\Infrastructure\Transformer\Shared\QueryParamTransformer\SortableTransformer;

class UserSortableTransformer extends SortableTransformer
{
    private const array SORTABLE_FIELDS = [
        'email' => 'email',
    ];

    public static function getSortableFields(): array
    {
        return self::SORTABLE_FIELDS;
    }

    /**
     * @throws InvalidSortException
     */
    public static function toSortableField(string $sortBy): SortableField
    {
        if (\array_key_exists($sortBy, LifeCycleSortableTransformer::getSortableFields())) {
            return LifeCycleSortableTransformer::toSortableField($sortBy);
        }

        if (!\array_key_exists($sortBy, self::SORTABLE_FIELDS)) {
            throw InvalidSortException::invalidField($sortBy);
        }

        return new SortableField(self::SORTABLE_FIELDS[$sortBy]);
    }
}
