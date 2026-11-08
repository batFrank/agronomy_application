<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Shared\Collection\Collection;
use App\Domain\Shared\Collection\CollectionException;
use App\Domain\Shared\Criteria\Condition\Condition;
use App\Domain\Shared\Criteria\Condition\FieldCondition;
use App\Domain\Shared\Criteria\Criteria;
use App\Domain\Shared\Criteria\CriteriaWithId;
use App\Domain\Shared\Criteria\Sort;
use App\Domain\Shared\Criteria\SortCollection;
use App\Domain\Shared\Criteria\SortDirection;
use App\Domain\Shared\Entity\Entity;
use App\Domain\Shared\Entity\EntityWithId;
use App\Domain\Shared\Uuid\Uuid;

class InMemoryCommonCriteriaBuilder
{
    /**
     * @template T
     * @template TCollection of Collection<T>
     *
     * @param Criteria<mixed> $criteria
     * @param TCollection     $collection
     *
     * @return TCollection
     *
     * @throws CollectionException
     */
    public static function filterByCommonCriteria(
        Criteria $criteria,
        Collection $collection,
        bool $onlyFilter = false,
    ): Collection {
        if ($criteria instanceof CriteriaWithId) {
            $collection = self::filterByCommonCriteriaWithId($criteria, $collection);
        }

        if ($onlyFilter) {
            return $collection;
        }

        $collection = self::applySorting($criteria, $collection);

        return self::applyPagination($criteria, $collection);
    }

    /**
     * @template T
     * @template TCollection of Collection<T>
     * @template TCriteriaWithId of CriteriaWithId
     *
     * @param CriteriaWithId<TCriteriaWithId> $criteria
     * @param TCollection                     $objects
     *
     * @return TCollection
     *
     * @throws CollectionException
     */
    private static function filterByCommonCriteriaWithId(CriteriaWithId $criteria, Collection $objects): Collection
    {
        return $objects->filter(
            fn (EntityWithId $object): bool => self::matchesId($criteria, $object)
                && self::matchesIds($criteria, $object)
        );
    }

    /**
     * @template T of CriteriaWithId
     *
     * @param CriteriaWithId<T> $criteria
     */
    private static function matchesId(CriteriaWithId $criteria, EntityWithId $object): bool
    {
        return null === $criteria->getId() || $object->getId()->equals($criteria->getId());
    }

    /**
     * @template T of CriteriaWithId
     *
     * @param CriteriaWithId<T> $criteria
     *
     * @throws CollectionException
     */
    private static function matchesIds(CriteriaWithId $criteria, EntityWithId $object): bool
    {
        $ids = $criteria->getIds();

        if (null === $ids || $ids->isEmpty()) {
            return true;
        }

        return !$ids->filter(
            fn (Uuid $id): bool => $object->getId()->equals($id)
        )->isEmpty();
    }

    /**
     * @template T
     * @template TCollection of Collection<T>
     *
     * @param Criteria<mixed> $criteria
     * @param TCollection     $collection
     *
     * @return TCollection
     *
     * @throws CollectionException
     */
    private static function applySorting(Criteria $criteria, Collection $collection): Collection
    {
        $sortBy = $criteria->getSortBy();
        if (!$sortBy) {
            return $collection;
        }

        $items = $collection->all();
        usort($items, fn (Entity $a, Entity $b): int => self::compareObjects($a, $b, $sortBy));

        return $collection->replace($items);
    }

    private static function compareObjects(Entity $object1, Entity $object2, SortCollection $sortBy): int
    {
        foreach ($sortBy->all() as $sortItem) {
            $result = self::compareByField($object1, $object2, $sortItem);
            if (0 !== $result) {
                return $result;
            }
        }

        return 0;
    }

    private static function compareByField(Entity $object1, Entity $object2, Sort $sortItem): int
    {
        $direction = $sortItem->getDirection();
        $fieldName = $sortItem->getField()->value();
        if (str_contains($fieldName, '_')) {
            $fieldName = lcfirst(
                str_replace(' ', '', ucwords(str_replace('_', ' ', $fieldName)))
            );
        }
        $fieldGetter = 'get' . ucfirst($fieldName);

        if (!method_exists($object1, $fieldGetter) || !method_exists($object2, $fieldGetter)) {
            return 0;
        }

        $value1 = $object1->$fieldGetter();
        $value2 = $object2->$fieldGetter();

        if ($value1 === $value2) {
            return 0;
        }

        $result = self::getComparisonResult($value1, $value2);

        return SortDirection::DESC === $direction ? -$result : $result;
    }

    private static function getComparisonResult(mixed $value1, mixed $value2): int
    {
        if (\is_string($value1) && \is_string($value2)) {
            return strcmp($value1, $value2);
        }

        return $value1 > $value2 ? 1 : -1;
    }

    /**
     * @template T
     * @template TCollection of Collection<T>
     *
     * @param Criteria<mixed> $criteria
     * @param TCollection     $collection
     *
     * @return TCollection
     *
     * @throws CollectionException
     */
    private static function applyPagination(Criteria $criteria, Collection $collection): Collection
    {
        $pagination = $criteria->getPagination();
        if (!$pagination) {
            return $collection;
        }

        $items = \array_slice(
            $collection->all(),
            $pagination->offset(),
            $pagination->limit()
        );

        return $collection->replace($items);
    }

    public static function validateCondition(mixed $value, FieldCondition $condition): bool
    {
        return match ($condition->getCondition()) {
            Condition::IsNull => null === $value,
            Condition::IsNotNull => null !== $value,
            Condition::NotEqual => \is_object($value)
                ? (
                    method_exists($value, 'equals')
                    ? !$value->equals($condition->value())
                    : $value != $condition->value()
                )
                : $value !== $condition->value(),
            Condition::Equal => \is_object($value)
                ? (
                    method_exists($value, 'equals')
                    ? $value->equals($condition->value())
                    : $value == $condition->value()
                )
                : $value === $condition->value(),
        };
    }
}
