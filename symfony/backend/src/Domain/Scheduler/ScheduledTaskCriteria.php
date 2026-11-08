<?php

declare(strict_types=1);

namespace App\Domain\Scheduler;

use App\Domain\Shared\Criteria\CriteriaWithId;

/**
 * @extends CriteriaWithId<ScheduledTaskCriteria>
 */
class ScheduledTaskCriteria extends CriteriaWithId
{
    private ?bool $enabled = null;
    private ?string $name = null;

    public function isEmpty(): bool
    {
        return parent::isEmpty()
            && null === $this->enabled
            && null === $this->name;
    }

    public function filterByEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getEnabled(): ?bool
    {
        return $this->enabled;
    }

    public function filterByName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }
}
