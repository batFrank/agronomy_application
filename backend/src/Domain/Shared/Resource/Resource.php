<?php

declare(strict_types=1);

namespace App\Domain\Shared\Resource;

use App\Domain\Shared\Uuid\Uuid;

final readonly class Resource
{
    public function __construct(
        private Uuid $id,
        private ResourceType $type,
    ) {
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getType(): ResourceType
    {
        return $this->type;
    }

    public function equals(self $entity): bool
    {
        return $this->id->equals($entity->id) && $this->type === $entity->type; // @phpstan-ignore identical.alwaysTrue
    }
}
