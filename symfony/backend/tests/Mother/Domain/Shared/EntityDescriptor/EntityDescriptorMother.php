<?php

declare(strict_types=1);

namespace App\Tests\Mother\Domain\Shared\EntityDescriptor;

use App\Domain\Shared\EntityDescriptor\EntityDescriptor;

final class EntityDescriptorMother
{
    private const string DEFAULT_NAME = 'Entity Name';
    private const string DEFAULT_DESCRIPTION = 'Entity Description';

    public static function create(
        ?string $name = null,
        ?string $description = null,
    ): EntityDescriptor {
        return new EntityDescriptor(
            name: $name ?? self::DEFAULT_NAME,
            description: $description ?? self::DEFAULT_DESCRIPTION,
        );
    }
}
