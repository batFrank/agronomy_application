<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Event;

use App\Domain\Shared\Event\DomainEvent;
use App\Infrastructure\Event\SymfonyDomainEventBus;
use App\Infrastructure\Event\SymfonyDomainEventEnvelope;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SymfonyDomainEventEnvelopeTest extends TestCase
{
    public function testItDispatchesAnEnvelopeUsingEventClassAsName(): void
    {
        /** @var EventDispatcherInterface&MockObject $dispatcher */
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $event = new class implements DomainEvent {};

        $dispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(
                self::callback(static function (object $dispatchedEvent) use ($event): bool {
                    if (!$dispatchedEvent instanceof SymfonyDomainEventEnvelope) {
                        return false;
                    }

                    return $dispatchedEvent->domainEvent() === $event;
                }),
                $event::class
            );

        $bus = new SymfonyDomainEventBus($dispatcher);
        $bus->publish($event);
    }
}
