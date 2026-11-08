<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Toolbox\DependencyInjection;

use App\Infrastructure\AI\Toolbox\DependencyInjection\StripIdentifiersFromToolboxesPass;
use App\Infrastructure\AI\Toolbox\IdentifierStrippingToolbox;
use App\Tests\Infrastructure\AI\Toolbox\Double\StubToolbox;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(StripIdentifiersFromToolboxesPass::class)]
class StripIdentifiersFromToolboxesPassTest extends TestCase
{
    /**
     * Fails if the pass registers a decorator although no toolbox is tagged, which would break the container.
     */
    public function testDoesNothingWhenNoToolboxIsTagged(): void
    {
        // Arrange
        $container = new ContainerBuilder();
        $container->addCompilerPass(new StripIdentifiersFromToolboxesPass());

        // Act
        $container->compile();

        // Assert
        $this->assertSame([], array_values(array_filter(
            array_keys($container->getDefinitions()),
            static fn (string $id): bool => str_starts_with($id, 'app.ai.identifier_stripping_toolbox.'),
        )));
    }

    public function testDecoratesTaggedToolboxAndSanitizesItsArrayResult(): void
    {
        // Arrange
        $container = new ContainerBuilder();
        $container->register('ai.toolbox.stub', StubToolbox::class)
            ->setArguments([['id' => 1, 'name' => 'Ana', 'created_by' => '3f2b8c1e-9a4d-4b7e-8c2a-1d5e6f7a8b9c']])
            ->addTag('ai.toolbox')
            ->setPublic(true);
        $container->addCompilerPass(new StripIdentifiersFromToolboxesPass());
        $container->compile();

        // Act
        $toolbox = $container->get('ai.toolbox.stub');
        $this->assertInstanceOf(IdentifierStrippingToolbox::class, $toolbox);
        $result = $toolbox->execute(new ToolCall('call-1', 'stub'));

        // Assert
        $this->assertSame(['name' => 'Ana'], $result->getResult());
    }
}
