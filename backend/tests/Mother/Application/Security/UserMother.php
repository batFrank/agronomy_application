<?php

declare(strict_types=1);

namespace App\Tests\Mother\Application\Security;

use App\Application\Security\User;
use App\Domain\Security\SecurityException;
use App\Domain\Shared\Email\Email;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Shared\Uuid\Uuid;
use App\Domain\User\UserRole;
use App\Tests\Mother\Domain\Shared\Email\EmailMother;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;

class UserMother
{
    /**
     * @param array<UserRole>|null $roles
     *
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     * @throws SecurityException
     */
    public static function create(
        ?Uuid $id = null,
        ?Email $email = null,
        ?array $roles = null,
    ): User {
        return new User(
            id: $id ?? UuidMother::create(),
            email: $email ?? EmailMother::create(),
            roles: $roles ?? [UserRole::User],
        );
    }
}
