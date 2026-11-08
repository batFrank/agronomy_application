<?php

declare(strict_types=1);

namespace App\Domain\Security;

use App\Domain\Shared\Criteria\Criteria;

/**
 * @extends Criteria<RefreshTokenCriteria>
 */
class RefreshTokenCriteria extends Criteria
{
    private ?string $token = null;
    private ?string $username = null;

    public function isEmpty(): bool
    {
        return null === $this->token;
    }

    public function filterByToken(string $token): self
    {
        $this->token = $token;

        return $this;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function filterByUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }
}
