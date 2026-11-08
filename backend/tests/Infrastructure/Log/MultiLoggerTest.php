<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Log;

use App\Application\Log\Logger;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Infrastructure\Log\MultiLogger;
use PHPUnit\Framework\TestCase;

class MultiLoggerTest extends TestCase
{
    public function testLogError(): void
    {
        $logger1 = $this->createMock(Logger::class);
        $logger2 = $this->createMock(Logger::class);

        $logger1->expects($this->once())
            ->method('error')->willReturnCallback(
                function (string $message, ?\Throwable $exception = null, array $extraData = []) {
                    $this->assertEquals('Test log message', $message);
                    $this->assertEquals(['test'], $extraData);
                    $this->assertEquals(new InfrastructureException(), $exception);
                }
            );

        $logger2->expects($this->once())
            ->method('error')->willReturnCallback(
                function (string $message, ?\Throwable $exception = null, array $extraData = []) {
                    $this->assertEquals('Test log message', $message);
                    $this->assertEquals(['test'], $extraData);
                    $this->assertEquals(new InfrastructureException(), $exception);
                }
            );

        $multiLogger = new MultiLogger(loggers: [$logger1, $logger2]);
        $multiLogger->error(message: 'Test log message', exception: new InfrastructureException(), extraData: ['test']);
    }

    public function testGetLogMessages(): void
    {
        $logger1 = $this->createMock(Logger::class);
        $logger2 = $this->createMock(Logger::class);

        $logger1->expects($this->once())
            ->method('getLogMessages')
            ->willReturn([
                'message 1',
                'message 2',
            ]);

        $logger2->expects($this->once())
            ->method('getLogMessages')
            ->willReturn([
                'message 3',
                'message 4',
            ]);

        $multiLogger = new MultiLogger(loggers: [$logger1, $logger2]);
        $result = $multiLogger->getLogMessages();
        $this->assertCount(4, $result);
        $this->assertEquals('message 1', $result[0]);
        $this->assertEquals('message 4', $result[3]);
    }
}
