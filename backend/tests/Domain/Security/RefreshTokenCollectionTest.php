<?php

declare(strict_types=1);

namespace App\Tests\Domain\Security;

use App\Domain\Security\RefreshToken;
use App\Domain\Security\RefreshTokenCollection;
use App\Domain\Shared\Collection\Collection;
use App\Domain\Shared\Collection\CollectionException;
use App\Tests\Domain\Shared\Collection\CollectionTestCase;
use App\Tests\Mother\Domain\Security\RefreshTokenMother;
use Random\RandomException;

/**
 * @extends CollectionTestCase<RefreshToken>
 */
class RefreshTokenCollectionTest extends CollectionTestCase
{
    /**
     * @param array<int, RefreshToken> $items
     *
     * @phpstan-return Collection<object>
     *
     * @throws CollectionException
     */
    protected function createCollection(array $items): Collection
    {
        /* @phpstan-ignore-next-line */
        return new RefreshTokenCollection($items);
    }

    protected function getExpectedType(): string
    {
        return RefreshToken::class;
    }

    /**
     * @throws RandomException
     */
    protected function getItem(): object
    {
        return RefreshTokenMother::create();
    }
}
