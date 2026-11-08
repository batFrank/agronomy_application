<?php

declare(strict_types=1);

namespace App\Domain\Shared\Entity;

use App\Domain\Shared\Uuid\Uuid;

class LifeCycleEntity extends EntityWithId
{
    public function __construct(
        Uuid $id,
        private readonly \DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $updatedAt = null,
        private ?\DateTimeImmutable $deletedAt = null,
    ) {
        parent::__construct($id);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function markAsUpdated(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function markAsDeleted(): void
    {
        $this->deletedAt = new \DateTimeImmutable();
    }

    public function restore(): void
    {
        $this->deletedAt = null;
    }
}
