<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Shared\Criteria\Criteria;
use App\Domain\Shared\Criteria\CriteriaWithId;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Query\QueryBuilder;

class DBALCommonCriteriaBuilder
{
    /**
     * @param Criteria<mixed>       $criteria
     * @param array<string, string> $sortMap
     */
    public static function filterByCommonCriteria(
        Criteria $criteria,
        QueryBuilder $queryBuilder,
        bool $onlyFilter = false,
        ?array $sortMap = null,
    ): void {
        if ($criteria instanceof CriteriaWithId) {
            self::filterByCommonCriteriaId($criteria, $queryBuilder);
        }

        if ($onlyFilter) {
            return;
        }

        if ($criteria->getPagination()) {
            $queryBuilder->setFirstResult($criteria->getPagination()->offset());
            $queryBuilder->setMaxResults($criteria->getPagination()->limit());
        }

        // Apply sorting if set
        $sortBy = $criteria->getSortBy();
        if (null !== $sortBy) {
            foreach ($sortBy->all() as $sortByItem) {
                $queryBuilder->addOrderBy(
                    't.' . DBALSortTransformer::fromSortByToDbalSortBy($sortByItem, $sortMap),
                    DBALSortTransformer::fromSortDirection($sortByItem->getDirection())
                );
            }
        } else {
            // Default sorting
            $queryBuilder->orderBy('t.id', 'ASC');
        }
    }

    /**
     * @template T of CriteriaWithId
     *
     * @param CriteriaWithId<T> $criteria
     */
    private static function filterByCommonCriteriaId(CriteriaWithId $criteria, QueryBuilder $queryBuilder): void
    {
        if ($criteria->getId()) {
            $queryBuilder->setParameter('id', $criteria->getId()->value());
            $queryBuilder->andWhere('t.id = :id');
        }

        if ($criteria->getIds() && !$criteria->getIds()->isEmpty()) {
            $queryBuilder->setParameter('ids', $criteria->getIds()->all(), ArrayParameterType::STRING);
            $queryBuilder->andWhere('t.id in (:ids)');
        }
    }
}
