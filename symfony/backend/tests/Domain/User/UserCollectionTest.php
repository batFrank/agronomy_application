<?php

declare(strict_types=1);

namespace App\Tests\Domain\User;

use App\Domain\Shared\Collection\Collection;
use App\Domain\Shared\Collection\CollectionException;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\User\User;
use App\Domain\User\UserCollection;
use App\Tests\Domain\Shared\Entity\EntityWithIdCollectionTestCase;
use App\Tests\Mother\Domain\User\UserMother;

/**
 * @extends EntityWithIdCollectionTestCase<User>
 */
class UserCollectionTest extends EntityWithIdCollectionTestCase
{
    /**
     * @param array<int, User> $items
     *
     * @phpstan-return Collection<object>
     *
     * @throws CollectionException
     */
    protected function createCollection(array $items): Collection
    {
        /* @phpstan-ignore-next-line */
        return new UserCollection($items);
    }

    protected function getExpectedType(): string
    {
        return User::class;
    }

    /**
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    protected function getItem(): object
    {
        return UserMother::create();
    }
}
