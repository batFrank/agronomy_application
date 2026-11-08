<?php

declare(strict_types=1);

namespace App\Tests\Functional\HelperTrait\Endpoints\Admin;

class UserEndpoints
{
    public static function getUsersEndpoint(): string
    {
        return '/admin/users';
    }

    public static function getUserEndpoint(string $id): string
    {
        return '/admin/users/' . $id;
    }

    public static function createUserEndpoint(): string
    {
        return '/admin/users';
    }

    public static function updateUserEndpoint(string $id): string
    {
        return '/admin/users/' . $id;
    }
}
