<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Translation;

use App\Domain\Shared\Collection\CollectionException;
use App\Domain\Translation\Exception\TranslationNotFoundException;
use App\Domain\Translation\Translation;
use App\Domain\Translation\TranslationCollection;
use App\Domain\Translation\TranslationCriteria;
use App\Domain\Translation\TranslationRepository;
use App\Infrastructure\Persistence\InMemoryCommonCriteriaBuilder;

class InMemoryTranslationRepository implements TranslationRepository
{
    private TranslationCollection $collection;

    /**
     * @throws CollectionException
     */
    public function __construct()
    {
        $this->collection = new TranslationCollection([]);
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function put(Translation $translation): void
    {
        $translations = $this->collection->allIndexedById();
        $translations[$translation->getId()->value()] = clone $translation;
        $this->collection->replace($translations);
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function findOneBy(TranslationCriteria $criteria): Translation
    {
        $translations = $this->filterByCriteria($criteria);

        if (null === $translations->first()) {
            throw new TranslationNotFoundException();
        }

        return $translations->first();
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function findBy(TranslationCriteria $criteria): TranslationCollection
    {
        return $this->filterByCriteria($criteria);
    }

    public function delete(Translation $translation): void
    {
        $this->findOneBy(
            TranslationCriteria::createById($translation->getId())
        );

        $translations = $this->collection->allIndexedById();
        unset($translations[$translation->getId()->value()]);
        $this->collection->replace($translations);
    }

    /**
     * @throws CollectionException
     */
    private function filterByCriteria(TranslationCriteria $criteria): TranslationCollection
    {
        $collection = $this->collection->filter(
            fn (Translation $translation) => null === $translation->getDeletedAt()
                && (
                    null === $criteria->getEntity()
                    || (
                        $translation->getEntityType() === $criteria->getEntity()::class
                        && $translation->getEntityId()->equals($criteria->getEntity()->getId())
                    )
                ) && (
                    null === $criteria->getEntityCollection()
                    || $criteria->getEntityCollection()->filter(
                        fn ($entity) => $translation->getEntityType() === $entity::class
                            && $translation->getEntityId()->equals($entity->getId())
                    )->count() > 0
                ) && (
                    null === $criteria->getField()
                    || $translation->getField() === $criteria->getField()
                ) && (
                    null === $criteria->getLocale()
                    || $translation->getLocale() === $criteria->getLocale()
                )
        );

        /** @var TranslationCollection $collection */
        $collection = InMemoryCommonCriteriaBuilder::filterByCommonCriteria($criteria, $collection);

        return $collection->map(
            fn (Translation $translation) => new Translation(
                id: $translation->getId(),
                entityType: $translation->getEntityType(),
                entityId: $translation->getEntityId(),
                field: $translation->getField(),
                locale: $translation->getLocale(),
                translation: $translation->getTranslation(),
                createdAt: $translation->getCreatedAt(),
                updatedAt: $translation->getUpdatedAt(),
                deletedAt: $translation->getDeletedAt(),
            )
        );
    }
}
