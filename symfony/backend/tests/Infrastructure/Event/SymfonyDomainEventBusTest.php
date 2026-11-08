<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Event;

use App\Domain\Shared\Event\DomainEvent;
use App\Infrastructure\Event\SymfonyDomainEventEnvelope;
use PHPUnit\Framework\TestCase;

class SymfonyDomainEventBusTest extends TestCase
{
    public function testItExposesTheOriginalDomainEvent(): void
    {
        $event = new class implements DomainEvent {};

        $envelope = new SymfonyDomainEventEnvelope($event);

        self::assertSame($event, $envelope->domainEvent());
    }

    public function testItIsNotStoppedByDefaultAndCanBeStopped(): void
    {
        $event = new class implements DomainEvent {};

        $envelope = new SymfonyDomainEventEnvelope($event);

        self::assertFalse($envelope->isPropagationStopped());

        $envelope->stopPropagation();

        self::assertTrue($envelope->isPropagationStopped());
    }
}
