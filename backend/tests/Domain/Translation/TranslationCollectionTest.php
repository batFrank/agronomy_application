<?php

declare(strict_types=1);

namespace App\Tests\Domain\Translation;

use App\Domain\Shared\Collection\CollectionException;
use App\Domain\Shared\Locale\Locale;
use App\Domain\Shared\Translation\Translatable;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Translation\Translation;
use App\Domain\Translation\TranslationCollection;
use App\Tests\Domain\Shared\Translation\TranslatableMock;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;
use App\Tests\Mother\Domain\Translation\TranslationMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TranslationCollectionTest extends TestCase
{
    protected function getExpectedType(): string
    {
        return Translation::class;
    }

    /**
     * @throws InvalidUuidException
     * @throws \Exception
     */
    protected function getItem(): Translation
    {
        return TranslationMother::create();
    }

    /**
     * @param Translation[] $items
     *
     * @throws CollectionException
     */
    protected function createCollection(array $items): TranslationCollection
    {
        return new TranslationCollection($items);
    }

    /**
     * @param array<string, Translation> $expectedIndexedEntities
     */
    #[DataProvider('filterEntityAndLocaleIndexedByFieldDataProvider')]
    public function testFilterEntityAndLocaleIndexedByField(
        TranslationCollection $collection,
        Translatable $translatable,
        Locale $locale,
        array $expectedIndexedEntities,
    ): void {
        $this->assertEquals(
            $expectedIndexedEntities,
            $collection->filterEntityAndLocaleIndexedByField($translatable, $locale)
        );
    }

    /**
     * @throws InvalidUuidException
     * @throws CollectionException
     */
    public static function filterEntityAndLocaleIndexedByFieldDataProvider(): \Generator
    {
        $translatable = new TranslatableMock(
            id: UuidMother::create(),
            name: 'Translatable name',
            description: 'Translatable description',
        );

        yield 'empty collection' => [
            'collection' => new TranslationCollection([]),
            'translatable' => $translatable,
            'locale' => Locale::ES,
            'expectedIndexedEntities' => [],
        ];

        $nameInBasque = TranslationMother::create(
            entityType: TranslatableMock::class,
            entityId: $translatable->getId(),
            field: 'name',
            locale: Locale::EN,
        );

        $nameInSpanish = TranslationMother::create(
            entityType: TranslatableMock::class,
            entityId: $translatable->getId(),
            field: 'name',
            locale: Locale::ES,
        );

        yield 'same field different locale' => [
            'collection' => new TranslationCollection([
                $nameInBasque,
                $nameInSpanish,
            ]),
            'translatable' => $translatable,
            'locale' => Locale::ES,
            'expectedIndexedEntities' => [
                'name' => $nameInSpanish,
            ],
        ];

        $otherTranslatable = new TranslatableMock(
            id: UuidMother::create(),
            name: 'Other translatable name',
            description: 'Other translatable description',
        );

        $otherNameInBasque = TranslationMother::create(
            entityType: TranslatableMock::class,
            entityId: $otherTranslatable->getId(),
            field: 'name',
            locale: Locale::EN,
        );

        yield 'different translatable' => [
            'collection' => new TranslationCollection([
                $nameInBasque,
                $otherNameInBasque,
            ]),
            'translatable' => $translatable,
            'locale' => Locale::EN,
            'expectedIndexedEntities' => [
                'name' => $nameInBasque,
            ],
        ];

        $descriptionInEnglish = TranslationMother::create(
            entityType: TranslatableMock::class,
            entityId: $translatable->getId(),
            field: 'description',
            locale: Locale::EN,
        );
        $otherDescriptionInEnglish = TranslationMother::create(
            entityType: TranslatableMock::class,
            entityId: $otherTranslatable->getId(),
            field: 'description',
            locale: Locale::EN,
        );

        yield 'different fields' => [
            'collection' => new TranslationCollection([
                $nameInBasque,
                $descriptionInEnglish,
                $otherNameInBasque,
                $nameInSpanish,
                $otherDescriptionInEnglish,
            ]),
            'translatable' => $translatable,
            'locale' => Locale::EN,
            'expectedIndexedEntities' => [
                'name' => $nameInBasque,
                'description' => $descriptionInEnglish,
            ],
        ];
    }

    /**
     * @param array<string, array<string, Translation>> $expectedIndexedEntities
     */
    #[DataProvider('filterByEntityTypeAndLocaleIndexedByEntityIdAndFieldDataProvider')]
    public function testFilterByEntityTypeAndLocaleIndexedByEntityIdAndField(
        TranslationCollection $collection,
        string $entityType,
        Locale $locale,
        array $expectedIndexedEntities,
    ): void {
        $this->assertEquals(
            $expectedIndexedEntities,
            $collection->filterByEntityTypeAndLocaleIndexedByEntityIdAndField($entityType, $locale)
        );
    }

    /**
     * @throws InvalidUuidException
     * @throws CollectionException
     */
    public static function filterByEntityTypeAndLocaleIndexedByEntityIdAndFieldDataProvider(): \Generator
    {
        $translatable = new TranslatableMock(
            id: UuidMother::create(),
            name: 'Translatable name',
            description: 'Translatable description',
        );

        $otherTranslatable = new TranslatableMock(
            id: UuidMother::create(),
            name: 'Other translatable name',
            description: 'Other translatable description',
        );

        yield 'empty collection' => [
            'collection' => new TranslationCollection([]),
            'entityType' => TranslatableMock::class,
            'locale' => Locale::EN,
            'expectedIndexedEntities' => [],
        ];

        $nameInEnglish = TranslationMother::create(
            entityType: TranslatableMock::class,
            entityId: $translatable->getId(),
            field: 'name',
            locale: Locale::EN,
        );

        $descriptionInEnglish = TranslationMother::create(
            entityType: TranslatableMock::class,
            entityId: $translatable->getId(),
            field: 'description',
            locale: Locale::EN,
        );

        $otherNameInEnglish = TranslationMother::create(
            entityType: TranslatableMock::class,
            entityId: $otherTranslatable->getId(),
            field: 'name',
            locale: Locale::EN,
        );

        $otherDescriptionInEnglish = TranslationMother::create(
            entityType: TranslatableMock::class,
            entityId: $otherTranslatable->getId(),
            field: 'description',
            locale: Locale::EN,
        );

        $otherNameInSpanish = TranslationMother::create(
            entityType: TranslatableMock::class,
            entityId: $otherTranslatable->getId(),
            field: 'name',
            locale: Locale::ES,
        );

        yield 'same entity type and locale' => [
            'collection' => new TranslationCollection([
                $nameInEnglish,
                $descriptionInEnglish,
                $otherNameInEnglish,
                $otherDescriptionInEnglish,
                $otherNameInSpanish,
            ]),
            'entityType' => TranslatableMock::class,
            'locale' => Locale::EN,
            'expectedIndexedEntities' => [
                $translatable->getId()->value() => [
                    'name' => $nameInEnglish,
                    'description' => $descriptionInEnglish,
                ],
                $otherTranslatable->getId()->value() => [
                    'name' => $otherNameInEnglish,
                    'description' => $otherDescriptionInEnglish,
                ],
            ],
        ];

        $translationForOtherEntityType = TranslationMother::create(
            entityType: 'OtherEntityType',
            entityId: $translatable->getId(),
            field: 'name',
            locale: Locale::EN,
        );

        yield 'different entity type' => [
            'collection' => new TranslationCollection([
                $nameInEnglish,
                $descriptionInEnglish,
                $otherNameInEnglish,
                $otherDescriptionInEnglish,
                $otherNameInSpanish,
                $translationForOtherEntityType,
            ]),
            'entityType' => TranslatableMock::class,
            'locale' => Locale::EN,
            'expectedIndexedEntities' => [
                $translatable->getId()->value() => [
                    'name' => $nameInEnglish,
                    'description' => $descriptionInEnglish,
                ],
                $otherTranslatable->getId()->value() => [
                    'name' => $otherNameInEnglish,
                    'description' => $otherDescriptionInEnglish,
                ],
            ],
        ];

        yield 'other locale' => [
            'collection' => new TranslationCollection([
                $nameInEnglish,
                $descriptionInEnglish,
                $otherNameInEnglish,
                $otherDescriptionInEnglish,
                $otherNameInSpanish,
            ]),
            'entityType' => TranslatableMock::class,
            'locale' => Locale::ES,
            'expectedIndexedEntities' => [
                $otherTranslatable->getId()->value() => [
                    'name' => $otherNameInSpanish,
                ],
            ],
        ];
    }
}
