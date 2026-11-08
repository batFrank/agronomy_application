<?php

declare(strict_types=1);

namespace App\Tests\Mother\Domain\ApiKey;

use App\Domain\ApiKey\ApiKey;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Shared\Uuid\Uuid;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;

class ApiKeyMother
{
    /**
     * @throws InvalidUuidException
     */
    public static function create(
        ?Uuid $id = null,
        ?string $apiKey = null,
        ?bool $active = null,
        ?Uuid $userId = null,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null,
        ?\DateTimeImmutable $deletedAt = null,
    ): ApiKey {
        return new ApiKey(
            id: $id ?? UuidMother::create(),
            apiKey: $apiKey ?? 'api-key-' . uniqid('', true),
            active: $active ?? true,
            userId: $userId,
            createdAt: $createdAt ?? new \DateTimeImmutable(),
            updatedAt: $updatedAt,
            deletedAt: $deletedAt,
        );
    }
}
