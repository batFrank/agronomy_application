<?php

declare(strict_types=1);

namespace App\Domain\Security;

use App\Domain\Shared\Collection\Collection;

/**
 * @extends Collection<RefreshToken>
 */
class RefreshTokenCollection extends Collection
{
    public function getTypeName(): string
    {
        return RefreshToken::class;
    }
}
