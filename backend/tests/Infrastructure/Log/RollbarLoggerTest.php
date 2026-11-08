<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Log;

use App\Infrastructure\Log\RollbarLogger;
use App\Infrastructure\Log\RollbarLoggerException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Only invalid tokens are covered here: they are rejected before Rollbar::init() runs, so these
 * tests never touch the global error handlers. Building the logger with a valid token is covered by
 * MultiLoggerFactoryTest, which already deals with the handlers Rollbar installs.
 */
class RollbarLoggerTest extends TestCase
{
    private const string VALID_32_TOKEN = 'u220csQuqcZJY46HRKlaj81QbqGcYV1j';

    #[DataProvider('invalidTokenProvider')]
    public function testItFailsWithAnActionableMessageOnAnInvalidToken(string $token): void
    {
        $this->expectException(RollbarLoggerException::class);
        $this->expectExceptionMessage(\sprintf(
            'ROLLBAR_TOKEN must be 32 or 96 characters long, got %d. Check your .env.local, or set ROLLBAR_LOGGER_ENABLED=false if you do not want to report errors to Rollbar.',
            \strlen($token)
        ));

        new RollbarLogger($token, 'test');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidTokenProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'too short' => ['tooshort'];
        yield 'one character short' => [substr(self::VALID_32_TOKEN, 1)];
        yield 'one character long' => [self::VALID_32_TOKEN . 'x'];
    }
}
