<?php

declare(strict_types=1);

namespace App\Domain\Shared\Criteria;

use App\Domain\Shared\Collection\Collection;
use App\Domain\Shared\Collection\CollectionException;
use App\Domain\Shared\Uuid\Uuid;
use App\Domain\Shared\Uuid\UuidCollection;

/**
 * @extends Criteria<CriteriaWithId>
 *
 * @template T of CriteriaWithId
 */
abstract class CriteriaWithId extends Criteria
{
    private ?Uuid $id = null;
    private ?UuidCollection $ids = null;

    protected function isEmpty(): bool
    {
        return null === $this->id && null === $this->ids;
    }

    public static function createById(Uuid $id): static
    {
        return self::createEmpty()->filterById($id);
    }

    /**
     * @param Collection<Uuid> $ids
     *
     * @throws CriteriaException
     * @throws CollectionException
     */
    public static function createByIds(Collection $ids): static
    {
        return self::createEmpty()->filterByIds($ids);
    }

    public function filterById(Uuid $id): static
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @param Collection<Uuid> $ids
     *
     * @throws CriteriaException
     * @throws CollectionException
     */
    public function filterByIds(Collection $ids): static
    {
        if (!$ids instanceof UuidCollection) {
            throw CollectionException::invalidCollectionType(
                expectedType: UuidCollection::class,
                actualType: \get_class($ids),
            );
        }

        if ($ids->isEmpty()) {
            throw CriteriaException::filterByEmptyIdsIsNotAllowed();
        }

        $this->ids = $ids;

        return $this;
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getIds(): ?UuidCollection
    {
        return $this->ids;
    }
}
