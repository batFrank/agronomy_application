<?php

declare(strict_types=1);

namespace App\Tests\Functional\HelperTrait\Endpoints;

class MeEndpoints
{
    public static function getMeEndpoint(): string
    {
        return '/me';
    }
}
