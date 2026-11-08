<?php

declare(strict_types=1);

namespace App\Domain\Security;

use App\Domain\Shared\Exception\UnauthorizedException;

class ApiKeyAuthenticationException extends UnauthorizedException
{
    public static function missingApiKey(): self
    {
        return new self('Missing API key.');
    }

    public static function invalidApiKey(): self
    {
        return new self('Invalid API key.');
    }

    public static function inactiveApiKey(): self
    {
        return new self('Inactive API key.');
    }
}
