<?php

declare(strict_types=1);

namespace App\Application\Query;

use App\Domain\Bus\Query;
use App\Domain\User\User;
use App\Domain\User\UserCriteria;

readonly class GetUser implements Query
{
    public function __construct(
        private UserCriteria $criteria,
        private ?User $requestingUser = null,
        private bool $needsCreator = false,
    ) {
    }

    public function getCriteria(): UserCriteria
    {
        return $this->criteria;
    }

    public function getRequestingUser(): ?User
    {
        return $this->requestingUser;
    }

    public function needsCreator(): bool
    {
        return $this->needsCreator;
    }
}
