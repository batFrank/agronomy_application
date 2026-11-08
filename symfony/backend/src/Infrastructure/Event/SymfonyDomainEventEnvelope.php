<?php

declare(strict_types=1);

namespace App\Infrastructure\Event;

use App\Domain\Shared\Event\DomainEvent;
use Psr\EventDispatcher\StoppableEventInterface;

final class SymfonyDomainEventEnvelope implements StoppableEventInterface
{
    private bool $propagationStopped = false;

    public function __construct(
        private readonly DomainEvent $domainEvent,
    ) {
    }

    public function domainEvent(): DomainEvent
    {
        return $this->domainEvent;
    }

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}
