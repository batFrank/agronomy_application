<?php

declare(strict_types=1);

namespace App\Tests\Mother\Domain\User;

use App\Domain\Shared\Email\Email;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Shared\Uuid\Uuid;
use App\Domain\User\User;
use App\Domain\User\UserRole;
use App\Tests\Mother\Domain\Shared\Email\EmailMother;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;

class UserMother
{
    /**
     * @param array<UserRole> $roles
     *
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    public static function create(
        ?Uuid $id = null,
        ?Email $email = null,
        ?array $roles = null,
        ?string $passwordHash = null,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null,
        ?\DateTimeImmutable $deletedAt = null,
        ?string $passwordResetToken = null,
        ?\DateTimeImmutable $passwordResetTokenExpiration = null,
        ?Uuid $createdBy = null,
    ): User {
        return new User(
            id: $id ?? UuidMother::create(),
            email: $email ?? EmailMother::create(),
            roles: $roles ?? [UserRole::User],
            passwordHash: $passwordHash ?? password_hash('password', PASSWORD_DEFAULT),
            createdAt: $createdAt ?? new \DateTimeImmutable(),
            updatedAt: $updatedAt,
            deletedAt: $deletedAt,
            passwordResetToken: $passwordResetToken,
            passwordResetTokenExpiration: $passwordResetTokenExpiration,
            createdBy: $createdBy,
        );
    }
}
