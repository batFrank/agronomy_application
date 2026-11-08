<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Log;

use App\Application\Log\Logger;
use App\Infrastructure\Log\MonologLogger;
use App\Infrastructure\Log\MultiLogger;
use App\Infrastructure\Log\MultiLoggerFactory;
use App\Infrastructure\Log\RollbarLogger;
use PHPUnit\Framework\TestCase;

class MultiLoggerFactoryTest extends TestCase
{
    private const string VALID_ROLLBAR_TOKEN = 'u220csQuqcZJY46HRKlaj81QbqGcYV1j';

    private bool $rollbarWasBuilt = false;

    protected function tearDown(): void
    {
        // Rollbar installs global error and exception handlers on init, so restore them only when
        // the logger was actually built: doing it unconditionally drops handlers we never set.
        if ($this->rollbarWasBuilt) {
            restore_error_handler();
            restore_exception_handler();
        }

        parent::tearDown();
    }

    public function testItDoesNotBuildRollbarWhenItIsNotIncluded(): void
    {
        $monolog = new MonologLogger('test');

        $multiLogger = (new MultiLoggerFactory())(
            monolog: $monolog,
            rollbar: static fn (): Logger => self::fail('Rollbar must not be built when it is not included'),
            includeRollbar: false,
        );

        $this->assertEquals(
            new MultiLogger([$monolog]),
            $multiLogger
        );
    }

    public function testItBuildsRollbarWhenItIsIncluded(): void
    {
        $monolog = new MonologLogger('test');
        $rollbar = new RollbarLogger(self::VALID_ROLLBAR_TOKEN, 'test');
        $this->rollbarWasBuilt = true;

        $multiLogger = (new MultiLoggerFactory())(
            monolog: $monolog,
            rollbar: static fn (): Logger => $rollbar,
            includeRollbar: true,
        );

        $this->assertEquals(
            new MultiLogger([$monolog, $rollbar]),
            $multiLogger
        );
    }
}
