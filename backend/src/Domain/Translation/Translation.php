<?php

declare(strict_types=1);

namespace App\Domain\Translation;

use App\Domain\Shared\Entity\LifeCycleEntity;
use App\Domain\Shared\Locale\Locale;
use App\Domain\Shared\Uuid\Uuid;

class git  extends LifeCycleEntity
{
    public function __construct(
        Uuid $id,
        private readonly string $entityType,
        private readonly Uuid $entityId,
        private readonly string $field,
        private readonly Locale $locale,
        private readonly string $translation,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $updatedAt = null,
        ?\DateTimeImmutable $deletedAt = null,
    ) {
        parent::__construct(
            id: $id,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            deletedAt: $deletedAt
        );
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function getEntityId(): Uuid
    {
        return $this->entityId;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getLocale(): Locale
    {
        return $this->locale;
    }

    public function getTranslation(): string
    {
        return $this->translation;
    }
}
