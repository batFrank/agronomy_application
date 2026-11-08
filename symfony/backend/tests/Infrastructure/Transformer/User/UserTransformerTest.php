<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Transformer\User;

use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\UserCard\UserCard;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\User\UserRole;
use App\Infrastructure\Transformer\User\UserTransformer;
use App\Tests\Mother\Domain\Shared\Email\EmailMother;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;
use App\Tests\Mother\Domain\User\UserMother;
use PHPUnit\Framework\TestCase;

class UserTransformerTest extends TestCase
{
    /**
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    public function testToArrayWithSingleRole(): void
    {
        $user = UserMother::create(
            email: EmailMother::create('user@example.com'),
            roles: [UserRole::User]
        );

        $result = UserTransformer::toArray($user);

        $this->assertSame($user->getId()->value(), $result['id']);
        $this->assertSame('user@example.com', $result['email']);
        $this->assertSame(['ROLE_USER'], $result['roles']);
    }

    /**
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    public function testToArrayWithMultipleRoles(): void
    {
        $user = UserMother::create(
            email: EmailMother::create('admin@example.com'),
            roles: [UserRole::User, UserRole::Admin, UserRole::Manager]
        );

        $result = UserTransformer::toArray($user);

        $this->assertSame($user->getId()->value(), $result['id']);
        $this->assertSame('admin@example.com', $result['email']);
        $this->assertSame(['ROLE_USER', 'ROLE_ADMIN', 'ROLE_MANAGER'], $result['roles']);
    }

    /**
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    public function testToArrayWithAdminOnlyRole(): void
    {
        $user = UserMother::create(
            email: EmailMother::create('superadmin@example.com'),
            roles: [UserRole::Admin]
        );

        $result = UserTransformer::toArray($user);

        $this->assertSame($user->getId()->value(), $result['id']);
        $this->assertSame('superadmin@example.com', $result['email']);
        $this->assertSame(['ROLE_ADMIN'], $result['roles']);
    }

    /**
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    public function testToArrayStructure(): void
    {
        $user = UserMother::create(
            email: EmailMother::create('test@example.com'),
            roles: [UserRole::User]
        );

        $result = UserTransformer::toArray($user);

        // Test that all expected keys are present
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('roles', $result);
        $this->assertArrayHasKey('created_at', $result);
        $this->assertArrayHasKey('updated_at', $result);
        $this->assertArrayHasKey('created_by', $result);

        // Test that expected keys match exactly
        $expectedKeys = ['id', 'email', 'roles', 'created_at', 'updated_at', 'created_by'];
        $this->assertSame($expectedKeys, array_keys($result));

        // Test data types
        $this->assertIsString($result['id']);
        $this->assertIsString($result['email']);
        $this->assertIsArray($result['roles']);
    }

    /**
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    public function testToArrayRolesAreStrings(): void
    {
        $user = UserMother::create(
            email: EmailMother::create('roles-test@example.com'),
            roles: [UserRole::User, UserRole::Admin]
        );

        $result = UserTransformer::toArray($user);

        // Test that all roles are strings
        foreach ($result['roles'] as $role) {
            $this->assertIsString($role);
            $this->assertStringStartsWith('ROLE_', $role);
        }
    }

    /**
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    public function testToArrayWithCreatedBy(): void
    {
        $createdByUuid = UuidMother::create();
        $user = UserMother::create(
            email: EmailMother::create('test@example.com'),
            roles: [UserRole::User],
            createdBy: $createdByUuid,
        );

        $result = UserTransformer::toArray($user);

        $this->assertSame($createdByUuid->value(), $result['created_by']);
    }

    /**
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    public function testToArrayWithoutCreatedByIsNull(): void
    {
        $user = UserMother::create(
            email: EmailMother::create('test@example.com'),
            roles: [UserRole::User],
        );

        $result = UserTransformer::toArray($user);

        $this->assertNull($result['created_by']);
    }

    /**
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    public function testToArrayWithCreator(): void
    {
        $creator = UserMother::create(email: EmailMother::create('creator@example.com'));
        $user = UserMother::create(createdBy: $creator->getId());
        $user->setCreator(new UserCard($creator->getId(), $creator->getEmail()));

        $result = UserTransformer::toArray($user);

        $this->assertSame([
            'id' => $creator->getId()->value(),
            'email' => 'creator@example.com',
        ], $result['creator']);
    }

    /**
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    public function testToArrayWithoutCreatorDoesNotExposeCreatorKey(): void
    {
        $user = UserMother::create();

        $result = UserTransformer::toArray($user);

        $this->assertArrayNotHasKey('creator', $result);
    }
}
