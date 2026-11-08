<?php

declare(strict_types=1);

namespace App\Domain\Shared\Entity;

use App\Domain\Shared\Exception\InfrastructureException;

class EntityWithIdCollectionException extends InfrastructureException
{
    public static function cannotGetIdsFromEmptyCollection(string $getTypeName): self
    {
        return new self(\sprintf('Cannot get IDs from an empty collection of type %s', $getTypeName));
    }
}
