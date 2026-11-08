<?php

declare(strict_types=1);

namespace App\Tests\Mother\Domain\Shared\UserCard;

use App\Domain\Shared\Email\Email;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\UserCard\UserCard;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Shared\Uuid\Uuid;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;

final class UserCardMother
{
    /**
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     */
    public static function create(
        ?Uuid $id = null,
        string $email = 'john.doe@example.com',
    ): UserCard {
        return new UserCard(
            id: null === $id ? UuidMother::create() : $id,
            email: new Email($email),
        );
    }
}
