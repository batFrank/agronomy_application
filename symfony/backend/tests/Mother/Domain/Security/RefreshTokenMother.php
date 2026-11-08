<?php

declare(strict_types=1);

namespace App\Tests\Mother\Domain\Security;

use App\Domain\Security\RefreshToken;
use Random\RandomException;

class RefreshTokenMother
{
    /**
     * @throws RandomException
     */
    public static function create(
        ?string $refreshToken = null,
        ?string $username = null,
        ?\DateTimeImmutable $valid = null,
    ): RefreshToken {
        return new RefreshToken(
            refreshToken: $refreshToken ?? bin2hex(random_bytes(64)),
            username: $username ?? 'test@example.com',
            valid: $valid ?? new \DateTimeImmutable('+1 hour'),
        );
    }
}
