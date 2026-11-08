<?php

declare(strict_types=1);

namespace App\Tests\Domain\ApiKey;

use App\Domain\ApiKey\ApiKey;
use App\Domain\ApiKey\ApiKeyCollection;
use App\Domain\Shared\Collection\Collection;
use App\Domain\Shared\Collection\CollectionException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Tests\Domain\Shared\Entity\EntityWithIdCollectionTestCase;
use App\Tests\Mother\Domain\ApiKey\ApiKeyMother;

/**
 * @extends EntityWithIdCollectionTestCase<ApiKey>
 */
class ApiKeyCollectionTest extends EntityWithIdCollectionTestCase
{
    /**
     * @param array<int, ApiKey> $items
     *
     * @phpstan-return Collection<object>
     *
     * @throws CollectionException
     */
    protected function createCollection(array $items): Collection
    {
        /* @phpstan-ignore-next-line */
        return new ApiKeyCollection($items);
    }

    protected function getExpectedType(): string
    {
        return ApiKey::class;
    }

    /**
     * @throws InvalidUuidException
     */
    protected function getItem(): object
    {
        return ApiKeyMother::create();
    }
}
