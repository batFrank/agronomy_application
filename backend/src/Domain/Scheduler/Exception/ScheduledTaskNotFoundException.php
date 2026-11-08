<?php

declare(strict_types=1);

namespace App\Domain\Scheduler\Exception;

use App\Domain\Shared\Exception\NotFoundException;

class ScheduledTaskNotFoundException extends NotFoundException
{
    public function __construct()
    {
        parent::__construct('Scheduled task not found');
    }
}
