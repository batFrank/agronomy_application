<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Toolbox;

use App\Infrastructure\AI\Toolbox\LogFailedToolCallSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\AI\Agent\Toolbox\Event\ToolCallFailed;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[CoversClass(LogFailedToolCallSubscriber::class)]
class LogFailedToolCallSubscriberContainerTest extends KernelTestCase
{
    /**
     * Fails if the subscriber is not autoconfigured: without it failures inside the bus leave no trace.
     */
    public function testSubscriberIsRegisteredForToolCallFailed(): void
    {
        // Arrange
        self::bootKernel();
        $dispatcher = self::getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        // Act
        $listeners = $dispatcher->getListeners(ToolCallFailed::class);

        // Assert
        $subscriberListeners = array_filter(
            $listeners,
            static fn (mixed $listener): bool => \is_array($listener)
                && $listener[0] instanceof LogFailedToolCallSubscriber
                && 'onToolCallFailed' === $listener[1],
        );
        self::assertCount(1, $subscriberListeners);
    }
}
