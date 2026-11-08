<?php

declare(strict_types=1);

namespace App\Domain\ApiKey;

use App\Domain\Shared\Entity\LifeCycleEntity;
use App\Domain\Shared\Uuid\Uuid;

class ApiKey extends LifeCycleEntity
{
    public function __construct(
        Uuid $id,
        private string $apiKey,
        private bool $active,
        private ?Uuid $userId,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $updatedAt = null,
        ?\DateTimeImmutable $deletedAt = null,
    ) {
        parent::__construct($id, $createdAt, $updatedAt, $deletedAt);
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getUserId(): ?Uuid
    {
        return $this->userId;
    }

    public function setApiKey(string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function setUserId(?Uuid $userId): void
    {
        $this->userId = $userId;
    }
}
