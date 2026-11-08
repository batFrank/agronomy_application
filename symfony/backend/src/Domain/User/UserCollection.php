<?php

declare(strict_types=1);

namespace App\Domain\User;

use App\Domain\Shared\Entity\EntityWithIdCollection;

/**
 * @extends EntityWithIdCollection<User>
 */
class UserCollection extends EntityWithIdCollection
{
    public function getTypeName(): string
    {
        return User::class;
    }
}
