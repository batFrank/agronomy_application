<?php

declare(strict_types=1);

namespace App\Domain\Security;

/**
 * Internal integrity failures of the security layer, outside the shared exception hierarchy on
 * purpose: they map to a 500. A denied permission is a ForbiddenException (403) and a missing
 * authentication an UnauthorizedException (401).
 */
class SecurityException extends \Exception
{
    public static function invalidUserIdentifier(): self
    {
        return new self('Invalid user identifier');
    }
}
