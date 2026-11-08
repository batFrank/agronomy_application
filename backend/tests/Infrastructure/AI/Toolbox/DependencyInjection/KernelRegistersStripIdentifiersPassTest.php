<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Toolbox\DependencyInjection;

use App\Infrastructure\AI\Toolbox\DependencyInjection\StripIdentifiersFromToolboxesPass;
use App\Kernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(Kernel::class)]
class KernelRegistersStripIdentifiersPassTest extends TestCase
{
    public function testBuildRegistersThePassThatStripsIdentifiersFromToolboxes(): void
    {
        // Arrange
        $kernel = new Kernel('test', true);
        $container = new ContainerBuilder();

        // Act
        new \ReflectionMethod($kernel, 'build')->invoke($kernel, $container);

        // Assert
        $registeredPasses = array_filter(
            $container->getCompilerPassConfig()->getBeforeOptimizationPasses(),
            static fn (object $pass): bool => $pass instanceof StripIdentifiersFromToolboxesPass,
        );
        self::assertCount(1, $registeredPasses);
    }
}
