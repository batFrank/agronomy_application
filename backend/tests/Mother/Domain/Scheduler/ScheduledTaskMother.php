<?php

declare(strict_types=1);

namespace App\Tests\Mother\Domain\Scheduler;

use App\Domain\Scheduler\ScheduledTask;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Shared\Uuid\Uuid;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;

class ScheduledTaskMother
{
    /**
     * @throws InvalidUuidException
     */
    public static function create(
        ?Uuid $id = null,
        ?string $name = null,
        ?string $frequency = null,
        ?string $messageClass = null,
        ?bool $enabled = null,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null,
        ?\DateTimeImmutable $deletedAt = null,
    ): ScheduledTask {
        return new ScheduledTask(
            id: $id ?? UuidMother::create(),
            name: $name ?? 'Test Task',
            frequency: $frequency ?? '1 hour',
            messageClass: $messageClass ?? 'App\Scheduler\Message\TestMessage',
            enabled: $enabled ?? true,
            createdAt: $createdAt ?? new \DateTimeImmutable(),
            updatedAt: $updatedAt,
            deletedAt: $deletedAt,
        );
    }
}
