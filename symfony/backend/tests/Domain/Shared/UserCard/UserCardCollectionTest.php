<?php

declare(strict_types=1);

namespace App\Tests\Domain\Shared\UserCard;

use App\Domain\Shared\Collection\Collection;
use App\Domain\Shared\Collection\CollectionException;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\UserCard\UserCard;
use App\Domain\Shared\UserCard\UserCardCollection;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Tests\Domain\Shared\Collection\CollectionTestCase;
use App\Tests\Mother\Domain\Shared\UserCard\UserCardMother;

/**
 * @extends CollectionTestCase<UserCard>
 */
class UserCardCollectionTest extends CollectionTestCase
{
    /**
     * @param array<int, UserCard> $items
     *
     * @phpstan-return Collection<object>
     *
     * @throws CollectionException
     */
    protected function createCollection(array $items): Collection
    {
        /* @phpstan-ignore-next-line */
        return new UserCardCollection($items);
    }

    protected function getExpectedType(): string
    {
        return UserCard::class;
    }

    /**
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     */
    protected function getItem(): object
    {
        return UserCardMother::create();
    }
}
