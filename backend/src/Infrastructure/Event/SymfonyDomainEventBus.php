<?php

declare(strict_types=1);

namespace App\Infrastructure\Event;

use App\Domain\Shared\Event\DomainEvent;
use App\Domain\Shared\Event\DomainEventBus;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final readonly class SymfonyDomainEventBus implements DomainEventBus
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function publish(DomainEvent $event): void
    {
        $envelope = new SymfonyDomainEventEnvelope($event);

        $this->dispatcher->dispatch($envelope, $event::class);
    }
}
