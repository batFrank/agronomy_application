<?php

declare(strict_types=1);

namespace App\Domain\Scheduler;

use App\Domain\Shared\Entity\LifeCycleEntity;
use App\Domain\Shared\Uuid\Uuid;

class ScheduledTask extends LifeCycleEntity
{
    public function __construct(
        Uuid $id,
        private readonly string $name,
        private readonly string $frequency,
        private readonly string $messageClass,
        private bool $enabled,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $updatedAt = null,
        ?\DateTimeImmutable $deletedAt = null,
    ) {
        parent::__construct($id, $createdAt, $updatedAt, $deletedAt);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getFrequency(): string
    {
        return $this->frequency;
    }

    public function getMessageClass(): string
    {
        return $this->messageClass;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function enable(): void
    {
        $this->enabled = true;
        $this->markAsUpdated();
    }

    public function disable(): void
    {
        $this->enabled = false;
        $this->markAsUpdated();
    }
}
