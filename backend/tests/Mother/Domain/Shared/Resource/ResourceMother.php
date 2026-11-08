<?php

declare(strict_types=1);

namespace App\Tests\Mother\Domain\Shared\Resource;

use App\Domain\Shared\Resource\Resource;
use App\Domain\Shared\Resource\ResourceType;
use App\Domain\Shared\Uuid\Uuid;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;

class ResourceMother
{
    public static function create(
        ?Uuid $id = null,
        ResourceType $type = ResourceType::User,
    ): Resource {
        return new Resource(
            id: $id ?? UuidMother::create(),
            type: $type,
        );
    }
}
