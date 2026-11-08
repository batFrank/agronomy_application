<?php

declare(strict_types=1);

namespace App\Infrastructure\Transformer\User;

use App\Domain\Shared\Collection\CollectionException;
use App\Domain\Shared\Criteria\Sort;
use App\Domain\Shared\Criteria\SortCollection;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Shared\Uuid\Uuid;
use App\Domain\User\UserCriteria;
use App\Infrastructure\Transformer\Shared\QueryParamTransformer\PaginationTransformer;

class UserCriteriaTransformer
{
    /**
     * @param array<string, mixed> $data
     *
     * @throws CollectionException
     * @throws InvalidUuidException
     */
    public static function fromArray(array $data): UserCriteria
    {
        $criteria = UserCriteria::createEmpty();

        if (isset($data['search'])) {
            $criteria->filterBySearchString($data['search']);
        }

        if (isset($data['role'])) {
            $criteria->filterByRole(RoleInputTransformer::toUserRole($data['role']));
        }

        if (isset($data['created_by'])) {
            $criteria->filterByCreatedBy(new Uuid($data['created_by']));
        }

        if (isset($data['sort_by'])) {
            $criteria->sortBy(
                new SortCollection(
                    [
                        new Sort(
                            UserSortableTransformer::toSortableField($data['sort_by']),
                            UserSortableTransformer::toSortDirection($data['sort_dir'])
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
