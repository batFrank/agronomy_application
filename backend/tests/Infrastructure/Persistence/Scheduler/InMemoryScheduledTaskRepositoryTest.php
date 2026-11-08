<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Persistence\Scheduler;

use App\Domain\Scheduler\ScheduledTaskRepository;
use App\Infrastructure\Persistence\Scheduler\InMemoryScheduledTaskRepository;
use App\Tests\Domain\Scheduler\ScheduledTaskRepositoryTestCase;

class InMemoryScheduledTaskRepositoryTest extends ScheduledTaskRepositoryTestCase
{
    protected function getRepository(): ScheduledTaskRepository
    {
        return new InMemoryScheduledTaskRepository();
    }
}
