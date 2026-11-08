<?php

declare(strict_types=1);

namespace App\Infrastructure\Log;

use App\Application\Log\Logger;

readonly class MultiLogger implements Logger
{
    /**
     * @param Logger[] $loggers
     */
    public function __construct(
        private array $loggers,
    ) {
    }

    /**
     * @param array<int, mixed>|array<string, mixed> $extraData
     */
    public function error(string $message, ?\Throwable $exception = null, array $extraData = []): void
    {
        foreach ($this->loggers as $logger) {
            $logger->error(
                message: $message,
                exception: $exception,
                extraData: $extraData
            );
        }
    }

    /**
     * @return array<int, mixed>
     */
    public function getLogMessages(): array
    {
        $data = [];
        foreach ($this->loggers as $logger) {
            $data = array_merge(
                $data,
                $logger->getLogMessages()
            );
        }

        return $data;
    }
}
