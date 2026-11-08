<?php

declare(strict_types=1);

namespace App\Tests\Domain\User;

use App\Domain\Shared\Email\Email;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\User\User;
use App\Domain\User\UserRole;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    /**
     * @throws InvalidUuidException
     */
    public function testUserCreation(): void
    {
        $id = UuidMother::create();
        $email = new Email('user@example.com');
        $roles = [UserRole::User, UserRole::Admin];
        $passwordHash = password_hash('password', PASSWORD_DEFAULT);
        $createdAt = new \DateTimeImmutable();

        $user = new User(
            id: $id,
            email: $email,
            roles: $roles,
            passwordHash: $passwordHash,
            createdAt: $createdAt,
        );

        $this->assertSame($id, $user->getId());
        $this->assertSame($email, $user->getEmail());
        $this->assertSame($roles, $user->getRoles());
        $this->assertTrue($user->hasRole(UserRole::User));
        $this->assertTrue($user->hasRole(UserRole::Admin));
        $this->assertSame($passwordHash, $user->getPasswordHash());
        // LifeCycleEntity removes microseconds from dates
        $this->assertEquals($createdAt->format('Y-m-d H:i:s'), $user->getCreatedAt()->format('Y-m-d H:i:s'));
        $this->assertNull($user->getUpdatedAt());
        $this->assertNull($user->getDeletedAt());
        $this->assertNull($user->getCreatedBy());
    }

    /**
     * @throws InvalidUuidException
     */
    public function testGetCreatedBy(): void
    {
        $createdByUuid = UuidMother::create();

        $user = new User(
            id: UuidMother::create(),
            email: new Email('user@example.com'),
            roles: [UserRole::User],
            passwordHash: password_hash('password', PASSWORD_DEFAULT),
            createdAt: new \DateTimeImmutable(),
            createdBy: $createdByUuid,
        );

        $this->assertSame($createdByUuid, $user->getCreatedBy());
    }

    /**
     * @throws InvalidUuidException
     */
    public function testCreatedByIsNullByDefault(): void
    {
        $user = new User(
            id: UuidMother::create(),
            email: new Email('user@example.com'),
            roles: [UserRole::User],
            passwordHash: password_hash('password', PASSWORD_DEFAULT),
            createdAt: new \DateTimeImmutable(),
        );

        $this->assertNull($user->getCreatedBy());
    }

    /**
     * @throws InvalidUuidException
     */
    public function testGetUserCard(): void
    {
        $id = UuidMother::create();
        $email = new Email('user@example.com');

        $user = new User(
            id: $id,
            email: $email,
            roles: [UserRole::User],
            passwordHash: password_hash('password', PASSWORD_DEFAULT),
            createdAt: new \DateTimeImmutable(),
        );

        $userCard = $user->getUserCard();

        $this->assertSame($id, $userCard->getId());
        $this->assertSame($email, $userCard->getEmail());
    }
}
