<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\Shared\Exception\ForbiddenException;

class FirewallException extends ForbiddenException
{
    public static function forbidden(): self
    {
        return new self('Forbidden');
    }
}
