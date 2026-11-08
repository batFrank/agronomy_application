<?php

declare(strict_types=1);

namespace App\Domain\Shared\UserCard;

use App\Domain\Shared\Collection\Collection;

/**
 * @extends Collection<UserCard>
 */
class UserCardCollection extends Collection
{
    public function getTypeName(): string
    {
        return UserCard::class;
    }
}
