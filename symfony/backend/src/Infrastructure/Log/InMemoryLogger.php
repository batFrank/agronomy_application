<?php

declare(strict_types=1);

namespace App\Infrastructure\Log;

use App\Application\Log\Logger;

class InMemoryLogger implements Logger
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $logMessages = [];

    /**
     * @param array<string, mixed> $extraData
     */
    public function error(string $message, ?\Throwable $exception = null, array $extraData = []): void
    {
        $this->logMessages[] = array_merge(
            ['message' => $message],
            $exception ? ['exception' => $exception] : [],
            $extraData,
        );
    }

    /**
     * @return array <int, array<string, mixed>>
     */
    public function getLogMessages(): array
    {
        return $this->logMessages;
    }
}
