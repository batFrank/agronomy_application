<?php

declare(strict_types=1);

namespace App\Domain\Translation;

use App\Domain\Shared\Entity\EntityWithIdCollection;
use App\Domain\Shared\Locale\Locale;
use App\Domain\Shared\Translation\Translatable;

/**
 * @extends EntityWithIdCollection<Translation>
 */
class TranslationCollection extends EntityWithIdCollection
{
    public function getTypeName(): string
    {
        return Translation::class;
    }

    /**
     * @return array<string, Translation>
     */
    public function filterEntityAndLocaleIndexedByField(Translatable $translatable, Locale $locale): array
    {
        $indexedEntities = [];

        foreach ($this->all() as $translation) {
            if (
                $translation->getLocale()->name !== $locale->name
                || $translation->getEntityType() !== $translatable->getEntityName()
                || !$translation->getEntityId()->equals($translatable->getId())
            ) {
                continue;
            }

            $indexedEntities[$translation->getField()] = $translation;
        }

        return $indexedEntities;
    }

    /**
     * @return array<string, array<string, Translation>>
     */
    public function filterByEntityTypeAndLocaleIndexedByEntityIdAndField(string $entityType, Locale $locale): array
    {
        $indexedEntities = [];

        foreach ($this->all() as $translation) {
            if (
                $translation->getEntityType() !== $entityType
                || $translation->getLocale() !== $locale
            ) {
                continue;
            }

            $indexedEntities[$translation->getEntityId()->value()][$translation->getField()] = $translation;
        }

        return $indexedEntities;
    }
}
