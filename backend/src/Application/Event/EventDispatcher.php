<?php

declare(strict_types=1);

namespace App\Application\Event;

use Psr\EventDispatcher\EventDispatcherInterface;

readonly class EventDispatcher implements EventDispatcherInterface
{
    public function __construct(private EventDispatcherInterface $eventDispatcher)
    {
    }

    public function dispatch(object $event): object
    {
        $this->eventDispatcher->dispatch($event);

        return $event;
    }
}
