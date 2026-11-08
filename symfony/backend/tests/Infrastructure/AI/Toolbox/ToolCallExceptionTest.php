<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Toolbox;

use App\Infrastructure\AI\Toolbox\ToolCallException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ToolCallException::class)]
class ToolCallExceptionTest extends \PHPUnit\Framework\TestCase
{
    public function testKeepsReasonAndPreviousWhenWithReason(): void
    {
        // Arrange
        $reason = 'The requested resource was not found.';
        $previous = new \RuntimeException('internal detail');

        // Act
        $exception = ToolCallException::withReason($reason, $previous);

        // Assert
        self::assertSame($reason, $exception->getToolCallResult());
        self::assertSame($reason, $exception->getMessage());
        self::assertSame($previous, $exception->getPrevious());
    }

    public function testUsesGenericMessageAndKeepsPreviousWhenUnexpected(): void
    {
        // Arrange
        $previous = new \RuntimeException('SQLSTATE[42P01] secret detail');

        // Act
        $exception = ToolCallException::unexpected($previous);

        // Assert
        self::assertSame('The operation could not be completed.', $exception->getToolCallResult());
        self::assertSame($previous, $exception->getPrevious());
    }
}
