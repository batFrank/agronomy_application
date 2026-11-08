<?php

declare(strict_types=1);

namespace App\Domain\Translation;

use App\Domain\Shared\Criteria\CriteriaWithId;
use App\Domain\Shared\Entity\EntityWithId;
use App\Domain\Shared\Entity\EntityWithIdCollection;
use App\Domain\Shared\Locale\Locale;

/**
 * @extends CriteriaWithId<TranslationCriteria>
 */
class TranslationCriteria extends CriteriaWithId
{
    private ?EntityWithId $entity = null;
    private ?EntityWithIdCollection $entityCollection = null; // @phpstan-ignore missingType.generics
    private ?string $field = null;
    private ?Locale $locale = null;

    public function isEmpty(): bool
    {
        return parent::isEmpty()
            && null === $this->entity
            && null === $this->entityCollection
            && null === $this->field
            && null === $this->locale;
    }

    public function filterByEntity(EntityWithId $entity): self
    {
        $this->entity = $entity;

        return $this;
    }

    /**
     * @template TEntity of EntityWithId
     *
     * @param EntityWithIdCollection<TEntity> $entityCollection
     */
    public function filterByEntityCollection(EntityWithIdCollection $entityCollection): self
    {
        $this->entityCollection = $entityCollection;

        return $this;
    }

    public function filterByField(string $field): self
    {
        $this->field = $field;

        return $this;
    }

    public function filterByLocale(Locale $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    public function getEntity(): ?EntityWithId
    {
        return $this->entity;
    }

    /**
     * @return EntityWithIdCollection<EntityWithId>|null
     */
    public function getEntityCollection(): ?EntityWithIdCollection
    {
        return $this->entityCollection;
    }

    public function getField(): ?string
    {
        return $this->field;
    }

    public function getLocale(): ?Locale
    {
        return $this->locale;
    }
}
