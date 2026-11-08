<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Log;

use App\Infrastructure\Log\MonologLogger;
use PHPUnit\Framework\TestCase;

class MonologLoggerTest extends TestCase
{
    private string $tempLogPath;
    private MonologLogger $logger;

    protected function setUp(): void
    {
        $this->tempLogPath = sys_get_temp_dir() . '/monolog_test_' . uniqid();
        mkdir($this->tempLogPath, 0777, true);
        $this->logger = new MonologLogger($this->tempLogPath);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempLogPath)) {
            $files = glob($this->tempLogPath . '/*');
            if (false !== $files) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
            rmdir($this->tempLogPath);
        }
    }

    public function testErrorLogging(): void
    {
        $message = 'Test error message';
        $exception = new \Exception('Test exception');
        $extraData = ['key' => 'value'];

        $this->logger->error($message, $exception, $extraData);

        $logMessages = $this->logger->getLogMessages();

        $this->assertNotEmpty($logMessages);
        $foundMessage = false;
        foreach ($logMessages as $logMessage) {
            if (str_contains($logMessage, $message)) {
                $foundMessage = true;
                break;
            }
        }
        $this->assertTrue($foundMessage);
    }

    public function testGetLogMessagesWithNoLogFile(): void
    {
        $nonExistentPath = sys_get_temp_dir() . '/non_existent_' . uniqid();
        $emptyLogger = new MonologLogger($nonExistentPath);

        $logMessages = $emptyLogger->getLogMessages();

        $this->assertEmpty($logMessages);
    }

    public function testGetLogMessagesFiltersEmptyLines(): void
    {
        $this->logger->error('Test message');

        $logFiles = glob($this->tempLogPath . '/app*.log');
        $this->assertNotEmpty($logFiles);
        $this->assertNotFalse($logFiles);

        file_put_contents($logFiles[0], "\n\n\n", FILE_APPEND);

        $logMessages = $this->logger->getLogMessages();

        foreach ($logMessages as $message) {
            $this->assertNotEmpty(trim($message));
        }
    }

    public function testLoggerUsesRotatingFileHandler(): void
    {
        $this->logger->error('Test message for rotation');

        $logFiles = glob($this->tempLogPath . '/app*.log');
        $this->assertNotEmpty($logFiles);
        $this->assertNotFalse($logFiles);

        $foundMessage = false;
        foreach ($logFiles as $logFile) {
            $content = file_get_contents($logFile);
            $this->assertNotFalse($content);
            if (str_contains($content, 'Test message for rotation')) {
                $foundMessage = true;
                break;
            }
        }
        $this->assertTrue($foundMessage);
    }

    public function testRotationBehaviorWithCorrectNaming(): void
    {
        $this->logger->error('Initial message');

        $logFiles = glob($this->tempLogPath . '/app*.log');
        $this->assertNotEmpty($logFiles);
        $this->assertNotFalse($logFiles);

        $hasCorrectPattern = false;
        foreach ($logFiles as $file) {
            $filename = basename($file);
            if ('app.log' === $filename || preg_match('/^app-\d{4}-\d{2}-\d{2}\.log$/', $filename)) {
                $hasCorrectPattern = true;
                break;
            }
        }
        $this->assertTrue($hasCorrectPattern);
    }

    public function testErrorLoggingWithDifferentExceptionTypes(): void
    {
        $exceptions = [
            new \InvalidArgumentException('Invalid argument'),
            new \RuntimeException('Runtime error'),
            new \LogicException('Logic error'),
        ];

        foreach ($exceptions as $exception) {
            $this->logger->error('Error: ' . \get_class($exception), $exception);
        }

        $logMessages = $this->logger->getLogMessages();
        $this->assertNotEmpty($logMessages);

        foreach ($exceptions as $exception) {
            $found = false;
            foreach ($logMessages as $message) {
                if (str_contains($message, \get_class($exception))) {
                    $found = true;
                    break;
                }
            }
            $this->assertTrue($found);
        }
    }
}
