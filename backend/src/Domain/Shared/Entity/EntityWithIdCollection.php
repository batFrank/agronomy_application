<?php

declare(strict_types=1);

namespace App\Domain\Shared\Entity;

use App\Domain\Shared\Collection\Collection;
use App\Domain\Shared\Collection\CollectionException;
use App\Domain\Shared\Uuid\UuidCollection;

/**
 * @template T of EntityWithId
 *
 * @extends Collection<T>
 */
abstract class EntityWithIdCollection extends Collection
{
    /**
     * @return array<string, T>
     */
    public function allIndexedById(): array
    {
        /**
         * @var T[] $entities
         */
        $entities = $this->all();
        $indexedEntities = [];

        foreach ($entities as $entity) {
            $indexedEntities[$entity->getId()->value()] = $entity;
        }

        return $indexedEntities;
    }

    /**
     * @throws CollectionException
     * @throws EntityWithIdCollectionException
     */
    public function allIds(): UuidCollection
    {
        /**
         * @var T[] $entities
         */
        $entities = $this->all();
        $ids = [];

        foreach ($entities as $entity) {
            $ids[] = $entity->getId();
        }

        if (empty($ids)) {
            throw EntityWithIdCollectionException::cannotGetIdsFromEmptyCollection(
                $this->getTypeName()
            );
        }

        return new UuidCollection($ids);
    }
}
