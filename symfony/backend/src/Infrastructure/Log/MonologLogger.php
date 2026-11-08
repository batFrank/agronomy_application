<?php

declare(strict_types=1);

namespace App\Infrastructure\Log;

use App\Application\Log\Logger;
use Monolog\Handler\RotatingFileHandler;

class MonologLogger implements Logger
{
    private \Monolog\Logger $logger;
    private const int RETENTION_DAYS = 14;

    public function __construct(private readonly string $logPath)
    {
        $this->logger = new \Monolog\Logger('app');

        $handler = new RotatingFileHandler(
            $this->logPath . DIRECTORY_SEPARATOR . 'app.log',
            self::RETENTION_DAYS,
            \Monolog\Logger::ERROR
        );

        // Use consistent filename format for rotated files
        $handler->setFilenameFormat('{filename}-{date}', RotatingFileHandler::FILE_PER_DAY);

        $this->logger->pushHandler($handler);
    }

    /**
     * @param array<int, mixed>|array<string, mixed> $extraData
     */
    public function error(string $message, ?\Throwable $exception = null, array $extraData = []): void
    {
        $this->logger->error($message, array_merge(
            $exception ? ['exception' => $exception] : [],
            $extraData,
        ));
    }

    /**
     * @return array<int, string>
     */
    public function getLogMessages(): array
    {
        $logMessages = [];

        // Get all log files (current and rotated) with single pattern
        $pattern = $this->logPath . DIRECTORY_SEPARATOR . 'app*.log';
        $allFiles = glob($pattern);
        if (false === $allFiles) {
            $allFiles = [];
        }

        foreach ($allFiles as $logFile) {
            if (file_exists($logFile)) {
                $logContent = file_get_contents($logFile);
                if (false !== $logContent && '' !== $logContent) {
                    $logMessages = array_merge($logMessages, explode(PHP_EOL, $logContent));
                }
            }
        }

        // Remove empty lines
        return array_filter($logMessages, fn (string $line): bool => '' !== trim($line));
    }
}
