<?php

declare(strict_types=1);

namespace App\Infrastructure\Log;

use App\Application\Log\Logger;
use Rollbar\Rollbar;

readonly class RollbarLogger implements Logger
{
    /**
     * Token lengths accepted by the Rollbar library.
     */
    private const array VALID_TOKEN_LENGTHS = [32, 96];

    /**
     * @throws RollbarLoggerException
     */
    public function __construct(
        private string $rollbarToken,
        private string $rollbarEnvironment,
    ) {
        $this->validateToken();

        Rollbar::init([
            'access_token' => $this->rollbarToken,
            'environment' => $this->rollbarEnvironment,
        ]);
    }

    /**
     * @param array<int, mixed>|array<string, mixed> $extraData
     */
    public function error(
        string $message,
        ?\Throwable $exception = null,
        array $extraData = []
    ): void {
        try {
            Rollbar::error(
                message: $message,
                context: array_merge(
                    $exception ? ['exception' => $exception] : [],
                    $extraData,
                )
            );
        } catch (\Throwable $e) {
            // Do nothing
        }
    }

    /**
     * @return array<int, mixed>
     */
    public function getLogMessages(): array
    {
        return [];
    }

    /**
     * @throws RollbarLoggerException
     */
    private function validateToken(): void
    {
        $length = \strlen($this->rollbarToken);

        if (\in_array($length, self::VALID_TOKEN_LENGTHS, true)) {
            return;
        }

        throw RollbarLoggerException::invalidTokenLength($length, self::VALID_TOKEN_LENGTHS);
    }
}
