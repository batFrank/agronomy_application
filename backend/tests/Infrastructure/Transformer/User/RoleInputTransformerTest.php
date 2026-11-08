<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Transformer\User;

use App\Domain\User\UserRole;
use App\Infrastructure\Transformer\User\RoleInputTransformer;
use PHPUnit\Framework\TestCase;

class RoleInputTransformerTest extends TestCase
{
    public function testToUserRoleTransformsKnownRoles(): void
    {
        $this->assertSame(UserRole::User, RoleInputTransformer::toUserRole('ROLE_USER'));
        $this->assertSame(UserRole::Manager, RoleInputTransformer::toUserRole('ROLE_MANAGER'));
        $this->assertSame(UserRole::Admin, RoleInputTransformer::toUserRole('ROLE_ADMIN'));
    }

    public function testToUserRoleReturnsUserForUnknownRole(): void
    {
        $this->assertSame(UserRole::User, RoleInputTransformer::toUserRole('ROLE_NOT_EXISTENT'));
    }

    public function testToUserRolesTransformsAllRolesKeepingOrder(): void
    {
        $result = RoleInputTransformer::toUserRoles(['ROLE_ADMIN', 'ROLE_MANAGER', 'ROLE_USER', 'ROLE_NOT_EXISTENT']);

        $this->assertSame([UserRole::Admin, UserRole::Manager, UserRole::User, UserRole::User], $result);
    }
}
