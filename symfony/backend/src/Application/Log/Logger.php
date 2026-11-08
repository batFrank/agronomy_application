<?php

declare(strict_types=1);

namespace App\Application\Log;

interface Logger
{
    /**
     * @param array<int, mixed>|array<string, mixed> $extraData
     */
    public function error(
        string $message,
        ?\Throwable $exception = null,
        array $extraData = [],
    ): void;

    /**
     * @return array<int, mixed>
     */
    public function getLogMessages(): array;
}
