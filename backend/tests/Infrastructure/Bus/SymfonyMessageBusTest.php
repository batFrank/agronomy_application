<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Bus;

use App\Domain\Bus\AsyncMessage;
use App\Domain\Bus\Exception\MessageBusException;
use App\Infrastructure\Bus\SymfonyMessageBus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class SymfonyMessageBusTest extends TestCase
{
    public function testItDelegatesToSymfonyMessageBus(): void
    {
        $message = new class implements AsyncMessage {};
        $envelope = new Envelope($message);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($message)
            ->willReturn($envelope);

        $symfonyMessageBus = $this->getSymfonyMessageBus($messageBus);

        $symfonyMessageBus->dispatch($message);
    }

    public function testItThrowsMessageBusExceptionWhenDispatchFails(): void
    {
        $message = new class implements AsyncMessage {};
        $originalException = new \RuntimeException('Dispatch failed', 500);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willThrowException($originalException);

        $symfonyMessageBus = $this->getSymfonyMessageBus($messageBus);

        try {
            $symfonyMessageBus->dispatch($message);
            $this->fail('Expected MessageBusException to be thrown');
        } catch (MessageBusException $e) {
            $this->assertSame('Dispatch failed', $e->getMessage());
            $this->assertSame(500, $e->getCode());
            $this->assertSame($originalException, $e->getPrevious());
        }
    }

    private function getSymfonyMessageBus(MessageBusInterface $messageBus): SymfonyMessageBus
    {
        return new SymfonyMessageBus($messageBus);
    }
}
