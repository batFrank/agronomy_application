<?php

declare(strict_types=1);

namespace App\Application\Translation;

use App\Domain\Shared\Entity\EntityWithId;
use App\Domain\Shared\Entity\EntityWithIdCollection;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Locale\Locale;
use App\Domain\Shared\Translation\Translatable;
use App\Domain\Shared\Translation\TranslatableCollection;
use App\Domain\Translation\Translation;
use App\Domain\Translation\TranslationCriteria;
use App\Domain\Translation\TranslationRepository;
use App\Domain\Translation\TranslationServiceInterface;

readonly class RepositoryTranslationService implements TranslationServiceInterface
{
    public function __construct(
        private TranslationRepository $translationRepository,
    ) {
    }

    /**
     * @throws InfrastructureException
     */
    #[\Override]
    public function translate(Translatable $translatable, Locale $locale): void
    {
        $this->translateFields($translatable, $locale);

        $this->translateDependencies($translatable, $locale);
    }

    /**
     * @throws InfrastructureException
     */
    private function translateFields(Translatable $translatable, Locale $locale): void
    {
        if (!$translatable instanceof EntityWithId) {
            return;
        }

        if (empty($translatable::getTranslatableFields())) {
            return;
        }

        $criteria = TranslationCriteria::createEmpty()
            ->filterByEntity($translatable)
            ->filterByLocale($locale);

        $translations = $this->translationRepository->findBy($criteria);

        if ($translations->isEmpty()) {
            return;
        }

        /** @var array<string, Translation> $indexedByField */
        $indexedByField = $translations->filterEntityAndLocaleIndexedByField(
            translatable: $translatable,
            locale: $locale,
        );

        foreach ($indexedByField as $field => $translation) {
            if (\in_array($field, $translatable::getTranslatableFields())) {
                $this->setTranslation($translatable, $field, $translation->getTranslation());
            }
        }
    }

    /**
     * @throws InfrastructureException
     */
    private function translateDependencies(Translatable $translatable, Locale $locale): void
    {
        $dependencies = $translatable::getTranslatableDependencies();

        if (empty($dependencies)) {
            return;
        }

        foreach ($dependencies as $dependency) {
            $getterMethod = 'get' . ucfirst($dependency);
            if (!method_exists($translatable, $getterMethod)) {
                continue;
            }

            $dependencyValue = $translatable->$getterMethod();

            if ($dependencyValue instanceof Translatable) {
                $this->translate($dependencyValue, $locale);
            } elseif ($dependencyValue instanceof TranslatableCollection) {
                $this->translateCollection($dependencyValue, $locale);
            }
        }
    }

    /**
     * @throws InfrastructureException
     */
    #[\Override]
    public function translateCollection(TranslatableCollection $collection, Locale $locale): void
    {
        if (
            !$collection instanceof EntityWithIdCollection
        ) {
            return;
        }

        if ($collection->isEmpty()) {
            return;
        }

        if (!$collection->first() instanceof Translatable) {
            return;
        }

        $criteria = TranslationCriteria::createEmpty()
            ->filterByEntityCollection($collection)
            ->filterByLocale($locale);

        $translations = $this->translationRepository->findBy($criteria);

        if ($translations->isEmpty()) {
            return;
        }

        $indexedTranslations = $translations->filterByEntityTypeAndLocaleIndexedByEntityIdAndField(
            entityType: $collection->getTypeName(),
            locale: $locale,
        );

        foreach ($collection->all() as $entity) {
            if (!isset($indexedTranslations[$entity->getId()->value()])) {
                continue;
            }

            if (!$entity instanceof Translatable) {
                continue;
            }

            // translate fields
            foreach ($indexedTranslations[$entity->getId()->value()] as $field => $translation) {
                $this->setTranslation($entity, $field, $translation->getTranslation());
            }

            // translate dependencies
            $this->translateDependencies($entity, $locale);
        }
    }

    private function setTranslation(Translatable $translatable, string $field, string $translation): void
    {
        $translatable->setTranslation($field, $translation);
    }
}
