<?php

declare(strict_types=1);

namespace App\Tests\Functional\HelperTrait\Endpoints;

class AuthEndpoints
{
    public static function loginEndpoint(): string
    {
        return '/auth/login';
    }

    public static function refreshTokenEndpoint(): string
    {
        return '/auth/refresh';
    }

    public static function logoutEndpoint(): string
    {
        return '/auth/logout';
    }

    public static function passwordRecoverEndpoint(): string
    {
        return '/auth/password-recover';
    }
}
