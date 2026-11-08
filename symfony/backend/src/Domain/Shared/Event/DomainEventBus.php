<?php

declare(strict_types=1);

namespace App\Domain\Shared\Event;

interface DomainEventBus
{
    public function publish(DomainEvent $event): void;
}
