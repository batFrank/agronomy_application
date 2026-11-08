<?php

declare(strict_types=1);

namespace App\Tests\Application\Translation;

use App\Application\Translation\RepositoryTranslationService;
use App\Domain\Shared\Collection\CollectionException;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Locale\Locale;
use App\Domain\Shared\Translation\Translatable;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Shared\Uuid\Uuid;
use App\Domain\Translation\TranslationCollection;
use App\Domain\Translation\TranslationCriteria;
use App\Domain\Translation\TranslationRepository;
use App\Tests\Domain\Shared\Translation\TranslatableMock;
use App\Tests\Domain\Shared\Translation\TranslatableMockCollection;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;
use App\Tests\Mother\Domain\Translation\TranslationMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

class RepositoryTranslationServiceTest extends TestCase
{
    private function getService(?TranslationRepository $translationRepository = null): RepositoryTranslationService
    {
        return new RepositoryTranslationService(
            $translationRepository ?? $this->createMock(TranslationRepository::class),
        );
    }

    /**
     * @throws InfrastructureException
     * @throws Exception
     */
    #[DataProvider('translateDoNothingWhenEntityNoFitsCriteriaDataProvider')]
    public function testTranslateDoNothingWhenEntityNoFitsCriteria(
        Translatable $translatable,
    ): void {
        $translationRepository = $this->createMock(TranslationRepository::class);
        $translationRepository->expects($this->never())->method('findBy');

        $service = $this->getService($translationRepository);
        $service->translate($translatable, Locale::ES);
    }

    public static function translateDoNothingWhenEntityNoFitsCriteriaDataProvider(): \Generator
    {
        $translatable = new class implements Translatable {
            public function getId(): Uuid
            {
                return new Uuid('00000000-0000-0000-0000-000000000000');
            }

            public static function getEntityName(): string
            {
                return self::class;
            }

            public static function getTranslatableFields(): array
            {
                return [];
            }

            public static function getTranslatableDependencies(): array
            {
                return [];
            }

            public function setTranslation(string $field, string $value): void
            {
            }
        };

        yield 'not entity with id' => [
            'translatable' => $translatable,
        ];

        $translatable = new class implements Translatable {
            public function getId(): Uuid
            {
                return UuidMother::create();
            }

            public static function getEntityName(): string
            {
                return self::class;
            }

            public static function getTranslatableFields(): array
            {
                return [];
            }

            public static function getTranslatableDependencies(): array
            {
                return [];
            }

            public function setTranslation(string $field, string $value): void
            {
            }
        };

        yield 'empty translatable fields and dependencies' => [
            'translatable' => $translatable,
        ];
    }

    /**
     * @throws InfrastructureException
     */
    public function testTranslatableFieldsAreTranslated(): void
    {
        $translatable = new TranslatableMock(
            id: UuidMother::create(),
            name: 'Original name',
            description: 'Original description',
        );

        $translationRepository = $this->createMock(TranslationRepository::class);
        $translationRepository
            ->expects($this->once())
            ->method('findBy')
            ->willReturnCallback(function (TranslationCriteria $criteria) use ($translatable) {
                $translations = new TranslationCollection([]);
                foreach ($translatable::getTranslatableFields() as $field) {
                    $translations->append(TranslationMother::create(
                        entityType: $translatable::getEntityName(),
                        entityId: $translatable->getId(),
                        field: $field,
                        locale: $criteria->getLocale() ?? Locale::ES,
                        translation: $field . ' translated to ' . $criteria->getLocale()?->name,
                    ));
                }

                return $translations;
            })
        ;

        $service = $this->getService($translationRepository);

        $service->translate($translatable, Locale::EN);

        $this->assertEquals('name translated to EN', $translatable->getName());
        $this->assertEquals('description translated to EN', $translatable->getDescription());
    }

    /**
     * @throws InfrastructureException
     */
    public function testTranslatableDependenciesAreTranslated(): void
    {
        $translatable = new TranslatableMock(
            id: UuidMother::create(),
            name: 'Original name',
            description: 'Original description',
        );

        $translatableDependency1 = new TranslatableMock(
            id: UuidMother::create(),
            name: 'Original dependency 1 name',
            description: 'Original dependency 1 description',
        );

        $translatableDependency2 = new TranslatableMock(
            id: UuidMother::create(),
            name: 'Original dependency 2 name',
            description: 'Original dependency 2 description',
        );

        $translatable->setTranslatableMockCollection(new TranslatableMockCollection([
            $translatableDependency1,
            $translatableDependency2,
        ]));

        $translationRepository = $this->createMock(TranslationRepository::class);
        $translationRepository
            ->expects($this->exactly(2))
            ->method('findBy')
            ->willReturnCallback(
                /**
                 * @throws CollectionException
                 * @throws InvalidUuidException
                 */
                function (TranslationCriteria $criteria): TranslationCollection {
                    $translations = new TranslationCollection([]);

                    if (null !== $criteria->getEntityCollection()) {
                        foreach ($criteria->getEntityCollection()->all() as $entity) {
                            if (!$entity instanceof Translatable) {
                                continue;
                            }
                            foreach ($entity::getTranslatableFields() as $field) {
                                $entityName = '';
                                if (method_exists($entity, 'getName')) {
                                    $entityName = $entity->getName();
                                }
                                $translations->append(TranslationMother::create(
                                    entityType: \get_class($entity),
                                    entityId: $entity->getId(),
                                    field: $field,
                                    locale: $criteria->getLocale() ?? Locale::ES,
                                    translation: $field . ' of ' . $entityName
                                        . ' translated to ' . $criteria->getLocale()?->name,
                                ));
                            }
                        }
                    }

                    return $translations;
                }
            )
        ;

        $service = $this->getService($translationRepository);

        $service->translate($translatable, Locale::ES);

        $this->assertEquals('name of Original dependency 1 name translated to ES', $translatableDependency1->getName());
        $this->assertEquals(
            'description of Original dependency 1 name translated to ES',
            $translatableDependency1->getDescription()
        );
        $this->assertEquals('name of Original dependency 2 name translated to ES', $translatableDependency2->getName());
        $this->assertEquals(
            'description of Original dependency 2 name translated to ES',
            $translatableDependency2->getDescription()
        );
    }
}
