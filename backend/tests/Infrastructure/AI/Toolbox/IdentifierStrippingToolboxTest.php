<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Toolbox;

use App\Infrastructure\AI\Toolbox\IdentifierStrippingToolbox;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\Source\Source;
use Symfony\AI\Agent\Toolbox\Source\SourceCollection;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

#[CoversClass(IdentifierStrippingToolbox::class)]
class IdentifierStrippingToolboxTest extends TestCase
{
    #[DataProvider('nonArrayResultProvider')]
    public function testPassesNonArrayResultsThroughUnchanged(mixed $result): void
    {
        // Arrange
        $toolCall = new ToolCall('call-1', 'probe');
        $toolResult = new ToolResult($toolCall, $result);
        $toolbox = $this->createInnerToolbox($toolResult);
        $decorated = new IdentifierStrippingToolbox($toolbox);

        // Act
        $actual = $decorated->execute($toolCall);

        // Assert
        $this->assertSame($toolResult, $actual);
    }

    public static function nonArrayResultProvider(): \Generator
    {
        yield 'string result' => ['plain text'];

        yield 'null result' => [null];
    }

    public function testSanitizesArrayResultAndKeepsToolCallAndSources(): void
    {
        // Arrange
        $toolCall = new ToolCall('call-1', 'probe');
        $sources = new SourceCollection([new Source('Title', 'https://example.com', 'Content')]);
        $toolbox = $this->createInnerToolbox(new ToolResult(
            $toolCall,
            ['id' => 1, 'name' => 'Ana', 'created_by' => '3f2b8c1e-9a4d-4b7e-8c2a-1d5e6f7a8b9c'],
            $sources,
        ));
        $decorated = new IdentifierStrippingToolbox($toolbox);

        // Act
        $result = $decorated->execute($toolCall);

        // Assert
        $this->assertSame(['name' => 'Ana'], $result->getResult());
        $this->assertSame($toolCall, $result->getToolCall());
        $this->assertSame($sources, $result->getSources());
    }

    public function testGetToolsDelegatesToInnerToolbox(): void
    {
        // Arrange
        $tools = [new Tool(new ExecutionReference(self::class, 'probe'), 'probe', 'Probe tool')];
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox->expects($this->once())
            ->method('getTools')
            ->willReturn($tools);
        $decorated = new IdentifierStrippingToolbox($toolbox);

        // Act
        $result = $decorated->getTools();

        // Assert
        $this->assertSame($tools, $result);
    }

    /**
     * @return ToolboxInterface&MockObject
     */
    private function createInnerToolbox(ToolResult $toolResult): ToolboxInterface
    {
        $toolbox = $this->createMock(ToolboxInterface::class);
        $toolbox->expects($this->once())
            ->method('execute')
            ->willReturn($toolResult);

        return $toolbox;
    }
}
