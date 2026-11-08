<?php

declare(strict_types=1);

namespace App\Infrastructure\Bus;

use App\Domain\Bus\AsyncMessage;
use App\Domain\Bus\Exception\MessageBusException;
use App\Domain\Bus\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class SymfonyMessageBus implements MessageBus
{
    public function __construct(private MessageBusInterface $messageBus)
    {
    }

    /**
     * @throws MessageBusException
     */
    public function dispatch(AsyncMessage $message): void
    {
        try {
            $this->messageBus->dispatch($message);
        } catch (\Throwable $e) {
            throw MessageBusException::fromPrevious($e);
        }
    }
}
