<?php

declare(strict_types=1);

namespace App\Domain\OTP;

use App\Domain\Shared\Entity\EntityWithId;
use App\Domain\Shared\Resource\Resource;
use App\Domain\Shared\Uuid\Uuid;

final class Otp extends EntityWithId
{
    public function __construct(
        Uuid $id,
        private readonly Resource $resource,
        private readonly Purpose $purpose,
        private readonly string $codeSecret,
        private readonly int $attempts,
        private readonly int $maxAttempts,
        private readonly bool $revoked,
        private readonly \DateTimeImmutable $expiresAt,
        private readonly ?\DateTimeImmutable $consumedAt,
        private readonly \DateTimeImmutable $createdAt,
    ) {
        parent::__construct($id);
    }

    public function getResource(): Resource
    {
        return $this->resource;
    }

    public function getPurpose(): Purpose
    {
        return $this->purpose;
    }

    public function getCodeSecret(): string
    {
        return $this->codeSecret;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function isRevoked(): bool
    {
        return $this->revoked;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getConsumedAt(): ?\DateTimeImmutable
    {
        return $this->consumedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
