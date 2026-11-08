<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Toolbox\DependencyInjection;

use App\Infrastructure\AI\Toolbox\IdentifierStrippingToolbox;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Decorates every `ai.toolbox` service with IdentifierStrippingToolbox.
 *
 * Priority -2048 makes it the outermost decorator: the bundle's FaultTolerantToolbox and TraceableToolbox
 * use -1024, and a lower priority is applied last, so the sanitized result is the one the agent receives.
 * Does nothing when no toolbox is tagged.
 */
final class StripIdentifiersFromToolboxesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach (array_keys($container->findTaggedServiceIds('ai.toolbox')) as $toolboxId) {
            $container->setDefinition(
                'app.ai.identifier_stripping_toolbox.' . $toolboxId,
                (new Definition(IdentifierStrippingToolbox::class))
                    ->setDecoratedService($toolboxId, null, -2048)
                    ->setArguments([new Reference('.inner')]),
            );
        }
    }
}
