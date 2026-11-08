<?php

declare(strict_types=1);

namespace App\Domain\Scheduler;

use App\Domain\Scheduler\Exception\ScheduledTaskNotFoundException;
use App\Domain\Shared\Exception\InfrastructureException;

interface ScheduledTaskRepository
{
    /**
     * @throws InfrastructureException
     */
    public function put(ScheduledTask $scheduledTask): void;

    /**
     * @throws InfrastructureException
     * @throws ScheduledTaskNotFoundException
     */
    public function findOneBy(ScheduledTaskCriteria $criteria): ScheduledTask;

    /**
     * @throws InfrastructureException
     */
    public function findBy(ScheduledTaskCriteria $criteria): ScheduledTaskCollection;

    /**
     * @throws InfrastructureException
     */
    public function countBy(ScheduledTaskCriteria $criteria): int;
}
