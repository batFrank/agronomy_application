<?php

declare(strict_types=1);

namespace App\Tests\Domain\Translation;

use App\Domain\Shared\Collection\CollectionException;
use App\Domain\Shared\Criteria\CriteriaException;
use App\Domain\Shared\Criteria\Sort;
use App\Domain\Shared\Criteria\SortableField;
use App\Domain\Shared\Criteria\SortCollection;
use App\Domain\Shared\Criteria\SortDirection;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Locale\Locale;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Shared\Uuid\UuidCollection;
use App\Domain\Translation\Exception\TranslationNotFoundException;
use App\Domain\Translation\Translation;
use App\Domain\Translation\TranslationCollection;
use App\Domain\Translation\TranslationCriteria;
use App\Domain\Translation\TranslationRepository;
use App\Tests\Domain\Shared\Translation\TranslatableMock;
use App\Tests\Domain\Shared\Translation\TranslatableMockCollection;
use App\Tests\Mother\Domain\Shared\Translation\TranslatableMockMother;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;
use App\Tests\Mother\Domain\Translation\TranslationMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

abstract class TranslationRepositoryTestCase extends TestCase
{
    abstract protected function getRepository(): TranslationRepository;

    /**
     * @throws InfrastructureException
     * @throws InvalidUuidException
     * @throws TranslationNotFoundException
     */
    public function testPutInsertNewTranslation(): void
    {
        $repository = $this->getRepository();

        $translations = $repository->findBy(TranslationCriteria::createEmpty());
        $this->assertTrue($translations->isEmpty());

        $translation = TranslationMother::create();
        $repository->put($translation);

        $translations = $repository->findBy(TranslationCriteria::createEmpty());
        $this->assertFalse($translations->isEmpty());

        $foundTranslation = $repository->findOneBy(
            TranslationCriteria::createById($translation->getId())
        );
        $this->assertEquals($translation, $foundTranslation);
        $this->assertNotSame($translation, $foundTranslation);
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidUuidException
     * @throws TranslationNotFoundException
     */
    public function testPutUpdateExistingTranslation(): void
    {
        $repository = $this->getRepository();

        $translation = TranslationMother::create(translation: 'Original');
        $repository->put($translation);

        $foundTranslation = $repository->findOneBy(
            TranslationCriteria::createById($translation->getId())
        );
        $this->assertEquals($translation, $foundTranslation);
        $this->assertNotSame($translation, $foundTranslation);

        $updatedTranslation = TranslationMother::create(
            id: $translation->getId(),
            translation: 'Updated',
            createdAt: $translation->getCreatedAt()
        );
        $repository->put($updatedTranslation);

        $foundTranslation = $repository->findOneBy(
            TranslationCriteria::createById($translation->getId())
        );
        $this->assertEquals($updatedTranslation, $foundTranslation);
        $this->assertNotSame($updatedTranslation, $foundTranslation);
    }

    /**
     * @throws InfrastructureException
     * @throws TranslationNotFoundException
     */
    #[DataProvider('findOneByThrowsExceptionWhenTranslationNotFoundProvider')]
    public function testFindOneByThrowsExceptionWhenTranslationNotFound(
        TranslationCollection $translations,
        TranslationCriteria $criteria,
    ): void {
        $repository = $this->getRepository();

        foreach ($translations->all() as $translation) {
            $repository->put($translation);
        }

        $this->expectException(TranslationNotFoundException::class);
        $repository->findOneBy($criteria);
    }

    /**
     * @throws CollectionException
     * @throws InvalidUuidException
     */
    public static function findOneByThrowsExceptionWhenTranslationNotFoundProvider(): \Generator
    {
        yield 'empty repository' => [
            'translations' => new TranslationCollection([]),
            'criteria' => TranslationCriteria::createById(UuidMother::create()),
        ];

        yield 'repository with one translation' => [
            'translations' => new TranslationCollection([TranslationMother::create()]),
            'criteria' => TranslationCriteria::createById(UuidMother::create()),
        ];
    }

    /**
     * @throws InfrastructureException
     * @throws TranslationNotFoundException
     */
    #[DataProvider('findOneByProvider')]
    public function testFindOneBy(
        TranslationCollection $translations,
        TranslationCriteria $criteria,
        Translation $expectedTranslation,
    ): void {
        $repository = $this->getRepository();

        foreach ($translations->all() as $translation) {
            $repository->put($translation);
        }

        $actualTranslation = $repository->findOneBy($criteria);
        $this->assertEquals($expectedTranslation, $actualTranslation);
    }

    /**
     * @throws InvalidUuidException
     * @throws CollectionException
     * @throws CriteriaException
     */
    public static function findOneByProvider(): \Generator
    {
        $entity1 = TranslatableMockMother::create();
        $entity2 = TranslatableMockMother::create();

        $translation1 = TranslationMother::create(
            id: UuidMother::create(),
            entityType: TranslatableMock::class,
            entityId: $entity1->getId(),
            field: 'name',
            locale: Locale::EN,
            translation: 'Name in basque for entity 1',
        );
        $translation2 = TranslationMother::create(
            id: UuidMother::create(),
            entityType: TranslatableMock::class,
            entityId: $entity1->getId(),
            field: 'name',
            locale: Locale::ES,
            translation: 'Name in Spanish for entity 1',
        );
        $translation3 = TranslationMother::create(
            id: UuidMother::create(),
            entityType: TranslatableMock::class,
            entityId: $entity1->getId(),
            field: 'description',
            locale: Locale::EN,
            translation: 'Description in basque for entity 1',
        );
        $translation4 = TranslationMother::create(
            id: UuidMother::create(),
            entityType: TranslatableMock::class,
            entityId: $entity2->getId(),
            field: 'name',
            locale: Locale::EN,
            translation: 'Name in Basque for entity 2',
        );

        $translations = new TranslationCollection([
            $translation1,
            $translation2,
            $translation3,
            $translation4,
        ]);

        yield 'by id' => [
            'translations' => $translations,
            'criteria' => TranslationCriteria::createById($translation1->getId()),
            'expectedTranslation' => $translation1,
        ];

        yield 'by ids' => [
            'translations' => $translations,
            'criteria' => TranslationCriteria::createByIds(
                new UuidCollection([$translation2->getId()])
            ),
            'expectedTranslation' => $translation2,
        ];

        yield 'by entity' => [
            'translations' => $translations,
            'criteria' => TranslationCriteria::createEmpty()->filterByEntity($entity2),
            'expectedTranslation' => $translation4,
        ];

        yield 'by entity collection' => [
            'translations' => $translations,
            'criteria' => TranslationCriteria::createEmpty()
                ->filterByEntityCollection(new TranslatableMockCollection([$entity2])),
            'expectedTranslation' => $translation4,
        ];

        yield 'by field' => [
            'translations' => $translations,
            'criteria' => TranslationCriteria::createEmpty()->filterByField('description'),
            'expectedTranslation' => $translation3,
        ];

        yield 'by locale' => [
            'translations' => $translations,
            'criteria' => TranslationCriteria::createEmpty()->filterByLocale(Locale::ES),
            'expectedTranslation' => $translation2,
        ];

        yield 'by entity and field' => [
            'translations' => $translations,
            'criteria' => TranslationCriteria::createEmpty()
                ->filterByEntity($entity1)
                ->filterByField('name')
                ->sortBy(
                    new SortCollection([
                        new Sort(
                            field: new SortableField('created_at'),
                            direction: SortDirection::ASC,
                        ),
                    ])
                ),
            'expectedTranslation' => $translation1,
        ];

        yield 'by entity and locale' => [
            'translations' => $translations,
            'criteria' => TranslationCriteria::createEmpty()
                ->filterByEntity($entity1)
                ->filterByLocale(Locale::ES),
            'expectedTranslation' => $translation2,
        ];

        yield 'by entity and field and locale' => [
            'translations' => $translations,
            'criteria' => TranslationCriteria::createEmpty()
                ->filterByEntity($entity1)
                ->filterByField('name')
                ->filterByLocale(Locale::ES),
            'expectedTranslation' => $translation2,
        ];

        yield 'by entity collection and field' => [
            'translations' => $translations,
            'criteria' => TranslationCriteria::createEmpty()
                ->filterByEntityCollection(new TranslatableMockCollection([$entity1]))
                ->filterByField('name')
                ->sortBy(
                    new SortCollection([
                        new Sort(
                            field: new SortableField('created_at'),
                            direction: SortDirection::ASC,
                        ),
                    ])
                ),
            'expectedTranslation' => $translation1,
        ];
    }

    /**
     * @throws InfrastructureException
     */
    #[DataProvider('findByProvider')]
    public function testFindBy(
        TranslationCollection $translations,
        TranslationCriteria $criteria,
        TranslationCollection $expectedTranslations,
    ): void {
        $repository = $this->getRepository();

        foreach ($translations->all() as $translation) {
            $repository->put($translation);
        }

        $actualTranslations = $repository->findBy($criteria);
        $this->assertEquals($expectedTranslations, $actualTranslations);
    }

    /**
     * @throws CollectionException
     * @throws CriteriaException
     * @throws InvalidUuidException
     */
    public static function findByProvider(): \Generator
    {
        $entity1 = TranslatableMockMother::create();
        $entity2 = TranslatableMockMother::create();

        $translation1 = TranslationMother::create(
            id: UuidMother::create(),
            entityType: TranslatableMock::class,
            entityId: $entity1->getId(),
            field: 'name',
            locale: Locale::EN,
            translation: 'Name in Basque for Entity 1',
        );
        $translation2 = TranslationMother::create(
            id: UuidMother::create(),
            entityType: TranslatableMock::class,
            entityId: $entity1->getId(),
            field: 'name',
            locale: Locale::ES,
            translation: 'Name in Spanish for Entity 1',
        );
        $translation3 = TranslationMother::create(
            id: UuidMother::create(),
            entityType: TranslatableMock::class,
            entityId: $entity1->getId(),
            field: 'description',
            locale: Locale::EN,
            translation: 'Description in Basque for Entity 1',
        );
        $translation4 = TranslationMother::create(
            id: UuidMother::create(),
            entityType: TranslatableMock::class,
            entityId: $entity2->getId(),
            field: 'name',
            locale: Locale::EN,
            translation: 'Name in Basque for Entity 2',
        );
        $translation5 = TranslationMother::create(
            id: UuidMother::create(),
            entityType: TranslatableMock::class,
            entityId: $entity2->getId(),
            field: 'name',
            locale: Locale::ES,
            translation: 'Name in Spanish for Entity 2',
        );

        $translations = new TranslationCollection([
            $translation1,
            $translation2,
            $translation3,
            $translation4,
            $translation5,
        ]);

        yield 'empty criteria' => [
            'translations' => $translations,
            'criteria' => TranslationCriteria::createEmpty()->sortBy(
                new SortCollection([
                    new Sort(
                        field: new SortableField('created_at'),
                        direction: SortDirection::ASC,
                    ),
                ])
            ),
            'expectedTranslations' => $translations,
        ];

        yield 'by id' => [
            'translations' => $translations,
            'criteria' => TranslationCriteria::createById($translation1->getId()),
            'expectedTranslations' => new TranslationCollection([
                $translation1,
            ]),
        ];

        yield 'by ids' => [
            'translations' => $translations,
            'criteria' => TranslationCriteria::createByIds(
                new UuidCollection([$translation2->getId(), $translation5->getId()])
            )->sortBy(
                new SortCollection([
                    new Sort(
                        field: new SortableField('created_at'),
                        direction: SortDirection::ASC,
                    ),
                ])
            ),
            'expectedTranslations' => new TranslationCollection([
                $translation2,
                $translation5,
            ]),
        ];

        yield 'by entity' => [
            'translations' => $translations,
            'criteria' => TranslationCriteria::createEmpty()->filterByEntity($entity1)->sortBy(
                new SortCollection([
                    new Sort(
                        field: new SortableField('created_at'),
                        direction: SortDirection::ASC,
                    ),
                ])
            ),
            'expectedTranslations' => new TranslationCollection([
                $translation1,
                $translation2,
                $translation3,
            ]),
        ];

        yield 'by entity collection' => [
            'translations' => $translations,
            'criteria' => TranslationCriteria::createEmpty()
                ->filterByEntityCollection(new TranslatableMockCollection([$entity2]))
                ->sortBy(
                    new SortCollection([
                        new Sort(
                            field: new SortableField('created_at'),
                            direction: SortDirection::ASC,
                        ),
                    ])
                ),
            'expectedTranslations' => new TranslationCollection([
                $translation4,
                $translation5,
            ]),
        ];

        yield 'by field' => [
            'translations' => $translations,
            'criteria' => TranslationCriteria::createEmpty()->filterByField('description'),
            'expectedTranslations' => new TranslationCollection([
                $translation3,
            ]),
        ];

        yield 'by locale' => [
            'translations' => $translations,
            'criteria' => TranslationCriteria::createEmpty()->filterByLocale(Locale::ES)->sortBy(
                new SortCollection([
                    new Sort(
                        field: new SortableField('created_at'),
                        direction: SortDirection::ASC,
                    ),
                ])
            ),
            'expectedTranslations' => new TranslationCollection([
                $translation2,
                $translation5,
            ]),
        ];

        yield 'by entity and field' => [
            'translations' => $translations,
            'criteria' => TranslationCriteria::createEmpty()
                ->filterByEntity($entity1)
                ->filterByField('name')
                ->sortBy(
                    new SortCollection([
                        new Sort(
                            field: new SortableField('created_at'),
                            direction: SortDirection::ASC,
                        ),
                    ])
                ),
            'expectedTranslations' => new TranslationCollection([
                $translation1,
                $translation2,
            ]),
        ];

        yield 'by entity and locale' => [
            'translations' => $translations,
            'criteria' => TranslationCriteria::createEmpty()
                ->filterByEntity($entity1)
                ->filterByLocale(Locale::EN)
                ->sortBy(
                    new SortCollection([
                        new Sort(
                            field: new SortableField('created_at'),
                            direction: SortDirection::ASC,
                        ),
                    ])
                ),
            'expectedTranslations' => new TranslationCollection([
                $translation1,
                $translation3,
            ]),
        ];

        yield 'by entity and field and locale' => [
            'translations' => $translations,
            'criteria' => TranslationCriteria::createEmpty()
                ->filterByEntity($entity1)
                ->filterByField('name')
                ->filterByLocale(Locale::EN),
            'expectedTranslations' => new TranslationCollection([
                $translation1,
            ]),
        ];

        yield 'by entity collection and field' => [
            'translations' => $translations,
            'criteria' => TranslationCriteria::createEmpty()
                ->filterByEntityCollection(new TranslatableMockCollection([$entity1, $entity2]))
                ->filterByField('name')
                ->sortBy(
                    new SortCollection([
                        new Sort(
                            field: new SortableField('created_at'),
                            direction: SortDirection::ASC,
                        ),
                    ])
                ),
            'expectedTranslations' => new TranslationCollection([
                $translation1,
                $translation2,
                $translation4,
                $translation5,
            ]),
        ];

        yield 'by entity collection and field and locale' => [
            'translations' => $translations,
            'criteria' => TranslationCriteria::createEmpty()
                ->filterByEntityCollection(new TranslatableMockCollection([$entity1, $entity2]))
                ->filterByField('name')
                ->filterByLocale(Locale::EN)
                ->sortBy(
                    new SortCollection([
                        new Sort(
                            field: new SortableField('created_at'),
                            direction: SortDirection::ASC,
                        ),
                    ])
                ),
            'expectedTranslations' => new TranslationCollection([
                $translation1,
                $translation4,
            ]),
        ];
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidUuidException
     * @throws TranslationNotFoundException
     */
    public function testDelete(): void
    {
        $repository = $this->getRepository();
        $translation = TranslationMother::create();
        $repository->put($translation);

        $repository->delete($translation);

        $this->expectException(TranslationNotFoundException::class);
        $repository->findOneBy(TranslationCriteria::createById($translation->getId()));
    }

    /**
     * @throws InfrastructureException
     */
    #[DataProvider('deleteThrowsExceptionWhenTranslationNotFoundProvider')]
    public function testDeleteThrowsExceptionWhenTranslationNotFound(
        TranslationCollection $translations,
        Translation $translationToDelete,
    ): void {
        $repository = $this->getRepository();

        foreach ($translations->all() as $translation) {
            $repository->put($translation);
        }

        $this->expectException(TranslationNotFoundException::class);
        $repository->delete($translationToDelete);
    }

    /**
     * @throws InvalidUuidException
     * @throws CollectionException
     */
    public static function deleteThrowsExceptionWhenTranslationNotFoundProvider(): \Generator
    {
        yield 'empty repository' => [
            'translations' => new TranslationCollection([]),
            'translationToDelete' => TranslationMother::create(),
        ];

        yield 'repository with translations' => [
            'translations' => new TranslationCollection([TranslationMother::create(), TranslationMother::create()]),
            'translationToDelete' => TranslationMother::create(),
        ];

        $deletedTranslation = TranslationMother::create(deletedAt: new \DateTimeImmutable());
        yield 'repository with previously deleted translation' => [
            'translations' => new TranslationCollection([$deletedTranslation]),
            'translationToDelete' => $deletedTranslation,
        ];
    }
}
