<?php

declare(strict_types=1);

namespace App\Tests\Mother\Domain\Translation;

use App\Domain\Shared\Locale\Locale;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Shared\Uuid\Uuid;
use App\Domain\Translation\Translation;
use App\Tests\Domain\Shared\Translation\TranslatableMock;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;

class TranslationMother
{
    public const string DEFAULT_ENTITY_TYPE = TranslatableMock::class;
    public const string DEFAULT_FIELD = 'name';
    public const Locale DEFAULT_LOCALE = Locale::ES;
    public const string DEFAULT_TRANSLATION = 'Translated text';

    /**
     * @throws InvalidUuidException
     * @throws \Exception
     */
    public static function create(
        ?Uuid $id = null,
        string $entityType = self::DEFAULT_ENTITY_TYPE,
        ?Uuid $entityId = null,
        string $field = self::DEFAULT_FIELD,
        Locale $locale = self::DEFAULT_LOCALE,
        string $translation = self::DEFAULT_TRANSLATION,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null,
        ?\DateTimeImmutable $deletedAt = null
    ): Translation {
        return new Translation(
            id: $id ?? UuidMother::create(),
            entityType: $entityType,
            entityId: $entityId ?? UuidMother::create(),
            field: $field,
            locale: $locale,
            translation: $translation,
            createdAt: $createdAt ?? new \DateTimeImmutable(),
            updatedAt: $updatedAt,
            deletedAt: $deletedAt
        );
    }
}
