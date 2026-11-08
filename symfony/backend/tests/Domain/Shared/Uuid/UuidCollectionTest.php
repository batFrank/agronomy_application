<?php

declare(strict_types=1);

namespace App\Tests\Domain\Shared\Uuid;

use App\Domain\Shared\Collection\Collection;
use App\Domain\Shared\Collection\CollectionException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Shared\Uuid\Uuid;
use App\Domain\Shared\Uuid\UuidCollection;
use App\Tests\Domain\Shared\Collection\CollectionTestCase;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;

/**
 * @extends CollectionTestCase<Uuid>
 */
class UuidCollectionTest extends CollectionTestCase
{
    /**
     * @param array<int, Uuid> $items
     *
     * @phpstan-return Collection<object>
     *
     * @throws CollectionException
     */
    protected function createCollection(array $items): Collection
    {
        /* @phpstan-ignore-next-line */
        return new UuidCollection($items);
    }

    protected function getExpectedType(): string
    {
        return Uuid::class;
    }

    /**
     * @throws InvalidUuidException
     */
    protected function getItem(): object
    {
        return UuidMother::create();
    }
}
