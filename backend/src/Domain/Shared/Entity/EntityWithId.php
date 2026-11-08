<?php

declare(strict_types=1);

namespace App\Domain\Shared\Entity;

use App\Domain\Shared\Uuid\Uuid;

abstract class EntityWithId implements Entity
{
    public function __construct(
        protected readonly Uuid $id,
    ) {
    }

    public function getId(): Uuid
    {
        return $this->id;
    }
}
