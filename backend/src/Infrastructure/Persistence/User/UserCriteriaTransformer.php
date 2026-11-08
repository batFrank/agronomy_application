<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\User;

use App\Domain\Shared\Collection\CollectionException;
use App\Domain\Shared\Criteria\Sort;
use App\Domain\Shared\Criteria\SortCollection;
use App\Domain\User\UserCriteria;
use App\Infrastructure\Transformer\Shared\QueryParamTransformer\PaginationTransformer;

class UserCriteriaTransformer
{
    /**
     * @param array<string, mixed> $data
     *
     * @throws CollectionException
     */
    public static function fromArray(array $data): UserCriteria
    {
        $criteria = UserCriteria::createEmpty();

        if (isset($data['search'])) {
            $criteria->filterBySearchString($data['search']);
        }

        if (isset($data['role'])) {
            $criteria->filterByRole($data['role']);
        }

        if (isset($data['sort_by'])) {
            $criteria->sortBy(
                new SortCollection(
                    [
                        new Sort(
                            UserSortableTransformer::toSortableField($data['sort_by']),
                            UserSortableTransformer::toSortDirection($data['sort_dir'] ?? 'asc')
                        ),
                    ]
                )
            );
        }

        $criteria->withPagination(
            PaginationTransformer::toPagination($data)
        );

        return $criteria;
    }
}
