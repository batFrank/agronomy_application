<?php

declare(strict_types=1);

namespace App\Domain\Shared\Collection;

use App\Domain\Shared\Exception\InfrastructureException;

class CollectionException extends InfrastructureException
{
    public static function invalidItemType(string $expectedType, string $actualType): self
    {
        return new self(\sprintf('Invalid collection item type. Expected: %s, actual: %s', $expectedType, $actualType));
    }

    public static function invalidCollectionType(string $expectedType, string $actualType): self
    {
        return new self(\sprintf('Invalid collection type. Expected: %s, actual: %s', $expectedType, $actualType));
    }

    public static function emptyCollection(): self
    {
        return new self('Collection is empty');
    }
}
