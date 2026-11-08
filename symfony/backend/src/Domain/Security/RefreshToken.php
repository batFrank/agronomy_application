<?php

declare(strict_types=1);

namespace App\Domain\Security;

readonly class RefreshToken
{
    public function __construct(
        private ?string $refreshToken = null,
        private ?string $username = null,
        private ?\DateTimeImmutable $valid = null,
    ) {
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getValid(): ?\DateTimeImmutable
    {
        return $this->valid;
    }
}
