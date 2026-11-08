<?php

declare(strict_types=1);

namespace App\Tests\Mother\Domain\OTP;

use App\Domain\OTP\Otp;
use App\Domain\OTP\Purpose;
use App\Domain\Shared\Resource\Resource;
use App\Domain\Shared\Uuid\Uuid;
use App\Tests\Mother\Domain\Shared\Resource\ResourceMother;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;

class OtpMother
{
    public static function create(
        ?Uuid $id = null,
        ?Resource $resource = null,
        Purpose $purpose = Purpose::Login2FA,
        ?string $codeSecret = null,
        int $attempts = 1,
        int $maxAttempts = 1,
        bool $revoked = false,
        // Wide on purpose: data providers are resolved before the suite runs, so a short window
        // expires while the tests that consume it are still queued.
        \DateTimeImmutable $expiresAt = new \DateTimeImmutable('+1 hour'),
        ?\DateTimeImmutable $consumedAt = null,
        \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ): Otp {
        return new Otp(
            id: $id ?? UuidMother::create(),
            resource: $resource ?? ResourceMother::create(),
            purpose: $purpose,
            codeSecret: $codeSecret ?? bin2hex(random_bytes(32)),
            attempts: $attempts,
            maxAttempts: $maxAttempts,
            revoked: $revoked,
            expiresAt: $expiresAt,
            consumedAt: $consumedAt,
            createdAt: $createdAt,
        );
    }
}
