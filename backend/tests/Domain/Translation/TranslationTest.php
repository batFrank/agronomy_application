<?php

declare(strict_types=1);

namespace App\Tests\Domain\Translation;

use App\Domain\Shared\Locale\Locale;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Translation\Translation;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;
use App\Tests\Mother\Domain\Translation\TranslationMother;
use PHPUnit\Framework\TestCase;

class TranslationTest extends TestCase
{
    /**
     * @throws InvalidUuidException
     * @throws \Exception
     */
    public function testCreateTranslationWithRequiredParameters(): void
    {
        $id = UuidMother::create();
        $entityId = UuidMother::create();
        $createdAt = new \DateTimeImmutable();

        $translation = new Translation(
            id: $id,
            entityType: 'Course',
            entityId: $entityId,
            field: 'title',
            locale: Locale::ES,
            translation: 'Curso de prueba',
            createdAt: $createdAt
        );

        $this->assertEquals($id, $translation->getId());
        $this->assertEquals('Course', $translation->getEntityType());
        $this->assertEquals($entityId, $translation->getEntityId());
        $this->assertEquals('title', $translation->getField());
        $this->assertEquals(Locale::ES, $translation->getLocale());
        $this->assertEquals('Curso de prueba', $translation->getTranslation());
        $this->assertEquals($createdAt, $translation->getCreatedAt());
        $this->assertNull($translation->getUpdatedAt());
        $this->assertNull($translation->getDeletedAt());
    }

    /**
     * @throws InvalidUuidException
     * @throws \Exception
     */
    public function testCreateTranslationWithAllParameters(): void
    {
        $id = UuidMother::create();
        $entityId = UuidMother::create();
        $createdAt = new \DateTimeImmutable('2023-01-01 10:00:00');
        $updatedAt = new \DateTimeImmutable('2023-01-02 10:00:00');
        $deletedAt = new \DateTimeImmutable('2023-01-03 10:00:00');

        $translation = new Translation(
            id: $id,
            entityType: 'Resource',
            entityId: $entityId,
            field: 'description',
            locale: Locale::EN,
            translation: 'Test course',
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            deletedAt: $deletedAt
        );

        $this->assertEquals($id, $translation->getId());
        $this->assertEquals('Resource', $translation->getEntityType());
        $this->assertEquals($entityId, $translation->getEntityId());
        $this->assertEquals('description', $translation->getField());
        $this->assertEquals(Locale::EN, $translation->getLocale());
        $this->assertEquals('Test course', $translation->getTranslation());
        $this->assertEquals($createdAt, $translation->getCreatedAt());
        $this->assertEquals($updatedAt, $translation->getUpdatedAt());
        $this->assertEquals($deletedAt, $translation->getDeletedAt());
    }

    /**
     * @throws InvalidUuidException
     * @throws \Exception
     */
    public function testTranslationInheritsLifeCycleMethods(): void
    {
        $translation = TranslationMother::create();

        // Test markAsUpdated
        $this->assertNull($translation->getUpdatedAt());
        $translation->markAsUpdated();
        $this->assertInstanceOf(\DateTimeImmutable::class, $translation->getUpdatedAt());

        // Test markAsDeleted
        $this->assertNull($translation->getDeletedAt());
        $translation->markAsDeleted();
        $this->assertInstanceOf(\DateTimeImmutable::class, $translation->getDeletedAt());

        // Test restore
        $translation->restore();
        $this->assertNull($translation->getDeletedAt());
    }

    /**
     * @throws InvalidUuidException
     * @throws \Exception
     */
    public function testTranslationWithUuidEntityId(): void
    {
        $id = UuidMother::create();
        $entityId = UuidMother::create();
        $createdAt = new \DateTimeImmutable();

        $translation = new Translation(
            id: $id,
            entityType: 'Filter',
            entityId: $entityId,
            field: 'name',
            locale: Locale::EN,
            translation: 'Examen de test',
            createdAt: $createdAt
        );

        $this->assertEquals($entityId, $translation->getEntityId());
        $this->assertEquals('Filter', $translation->getEntityType());
        $this->assertEquals('name', $translation->getField());
    }
}
