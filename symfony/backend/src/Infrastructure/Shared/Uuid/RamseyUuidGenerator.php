<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Uuid;

use App\Domain\Shared\Uuid\Uuid;
use App\Domain\Shared\Uuid\UuidGenerator;

class RamseyUuidGenerator implements UuidGenerator
{
    public function generate(): Uuid
    {
        return new Uuid(\Ramsey\Uuid\Uuid::uuid7()->toString());
    }
}
