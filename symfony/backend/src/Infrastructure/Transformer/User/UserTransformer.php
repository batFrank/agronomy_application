<?php

declare(strict_types=1);

namespace App\Infrastructure\Transformer\User;

use App\Domain\User\User;
use App\Domain\User\UserRole;
use App\Infrastructure\Transformer\DateTimeTransformer;

class UserTransformer
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(User $user): array
    {
        return array_merge(
            [
                'id' => $user->getId()->value(),
                'email' => $user->getEmail()->value(),
                'roles' => self::transformRoles($user->getRoles()),
                'created_at' => DateTimeTransformer::toOutput($user->getCreatedAt()),
                'updated_at' => $user->getUpdatedAt() ? DateTimeTransformer::toOutput($user->getUpdatedAt()) : null,
                'created_by' => $user->getCreatedBy()?->value(),
            ],
            (null !== $user->getCreator()) ?
                [
                    'creator' => [
                        'id' => $user->getCreator()->getId()->value(),
                        'email' => $user->getCreator()->getEmail()->value(),
                    ],
                ]
                : []
        );
    }

    /**
     * @param UserRole[] $roles
     *
     * @return string[]
     */
    private static function transformRoles(array $roles): array
    {
        return array_map(
            fn (UserRole $role) => self::transformRole($role),
            $roles
        );
    }

    private static function transformRole(UserRole $getRole): string
    {
        return match ($getRole) {
            UserRole::User => 'ROLE_USER',
            UserRole::Admin => 'ROLE_ADMIN',
            UserRole::Manager => 'ROLE_MANAGER',
        };
    }
}
