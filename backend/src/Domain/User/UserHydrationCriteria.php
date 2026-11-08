<?php

declare(strict_types=1);

namespace App\Domain\User;

use App\Domain\Shared\Hydrator\HydrationCriteria;

class UserHydrationCriteria extends HydrationCriteria
{
    private bool $withCreator = false;

    public function withCreator(): self
    {
        $this->withCreator = true;

        return $this;
    }

    public function shouldHydrateCreator(): bool
    {
        return $this->withCreator;
    }
}
