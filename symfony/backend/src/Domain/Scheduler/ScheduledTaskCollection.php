<?php

declare(strict_types=1);

namespace App\Domain\Scheduler;

use App\Domain\Shared\Entity\EntityWithIdCollection;

/**
 * @extends EntityWithIdCollection<ScheduledTask>
 */
class ScheduledTaskCollection extends EntityWithIdCollection
{
    public function getTypeName(): string
    {
        return ScheduledTask::class;
    }
}
