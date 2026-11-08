<?php

declare(strict_types=1);

namespace App\Infrastructure\Transformer\User;

use App\Domain\User\UserRole;

class RoleInputTransformer
{
    private const array ROLE_MAP = [
        'ROLE_USER' => UserRole::User,
        'ROLE_MANAGER' => UserRole::Manager,
        'ROLE_ADMIN' => UserRole::Admin,
    ];

    public static function toUserRole(string $role): UserRole
    {
        return self::ROLE_MAP[$role] ?? UserRole::User;
    }

    /**
     * @param string[] $roles
     *
     * @return UserRole[]
     */
    public static function toUserRoles(array $roles): array
    {
        return array_map(
            fn (string $role) => self::toUserRole($role),
            $roles
        );
    }
}
