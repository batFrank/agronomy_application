<?php

declare(strict_types=1);

namespace App\Domain\ApiKey;

use App\Domain\Shared\Criteria\CriteriaWithId;

/**
 * @extends CriteriaWithId<ApiKeyCriteria>
 */
class ApiKeyCriteria extends CriteriaWithId
{
    private ?string $key = null;

    public function isEmpty(): bool
    {
        return parent::isEmpty() && null === $this->key;
    }

    public function filterByKey(string $key): self
    {
        $this->key = $key;

        return $this;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }
}
