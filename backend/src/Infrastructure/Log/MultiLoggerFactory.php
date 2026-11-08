<?php

declare(strict_types=1);

namespace App\Infrastructure\Log;

use App\Application\Log\Logger;

class MultiLoggerFactory
{
    /**
     * The Rollbar logger arrives as a closure so that it is only built when it is going to be used:
     * its constructor initializes the Rollbar library, which throws on an invalid token.
     *
     * @param \Closure(): Logger $rollbar
     */
    public function __invoke(
        Logger $monolog,
        \Closure $rollbar,
        bool $includeRollbar = false,
    ): MultiLogger {
        $loggers = [
            $monolog,
        ];

        if ($includeRollbar) {
            $loggers[] = $rollbar();
        }

        return new MultiLogger(
            loggers: $loggers,
        );
    }
}
