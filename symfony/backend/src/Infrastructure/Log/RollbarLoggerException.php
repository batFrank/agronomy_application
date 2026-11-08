<?php

declare(strict_types=1);

namespace App\Infrastructure\Log;

use App\Domain\Shared\Exception\InfrastructureException;

class RollbarLoggerException extends InfrastructureException
{
    /**
     * @param int[] $validLengths
     */
    public static function invalidTokenLength(int $length, array $validLengths): self
    {
        return new self(\sprintf(
            'ROLLBAR_TOKEN must be %s characters long, got %d. Check your .env.local, or set ROLLBAR_LOGGER_ENABLED=false if you do not want to report errors to Rollbar.',
            implode(' or ', $validLengths),
            $length,
        ));
    }
}
