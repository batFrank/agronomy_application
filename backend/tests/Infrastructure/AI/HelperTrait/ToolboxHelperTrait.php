<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\HelperTrait;

use App\Domain\Bus\CommandBus;
use App\Domain\Bus\QueryBus;
use App\Infrastructure\AI\Context\AgentContext;
use App\Infrastructure\AI\Toolbox\IdentifierStrippingToolbox;
use App\Infrastructure\AI\Toolbox\ToolExceptionMapper;
use App\Tests\Infrastructure\AI\Toolbox\Double\ProbeTool;
use PHPUnit\Framework\Assert;
use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\Toolbox\FaultTolerantToolbox;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolCallArgumentResolverInterface;
use Symfony\AI\Agent\Toolbox\ToolFactoryInterface;
use Symfony\AI\Agent\Toolbox\ToolResultConverter;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

trait ToolboxHelperTrait
{
    protected function createToolbox(object ...$tools): ToolboxInterface
    {
        $container = $this->getContainer();
        $factory = $container->get('test.ai.tool_factory');
        $resolver = $container->get('test.ai.tool_call_argument_resolver');
        $logger = $container->get('logger');
        $eventDispatcher = $container->get('event_dispatcher');
        Assert::assertInstanceOf(ToolFactoryInterface::class, $factory);
        Assert::assertInstanceOf(ToolCallArgumentResolverInterface::class, $resolver);
        Assert::assertInstanceOf(LoggerInterface::class, $logger);
        Assert::assertInstanceOf(EventDispatcherInterface::class, $eventDispatcher);

        return new IdentifierStrippingToolbox(
            new FaultTolerantToolbox(new Toolbox($tools, $factory, $resolver, $logger, $eventDispatcher)),
        );
    }

    /**
     * Returns what the model receives as the tool result.
     *
     * @param array<string, mixed> $arguments
     */
    protected function callTool(ToolboxInterface $toolbox, string $name, array $arguments = []): ?string
    {
        return (new ToolResultConverter())->convert($toolbox->execute(new ToolCall('call-' . $name, $name, $arguments)));
    }

    protected function createProbeTool(?\Throwable $failure = null): ProbeTool
    {
        $container = $this->getContainer();
        $queryBus = $container->get(QueryBus::class);
        $commandBus = $container->get(CommandBus::class);
        $context = $container->get('test.agent_context');
        $mapper = $container->get('test.tool_exception_mapper');
        Assert::assertInstanceOf(QueryBus::class, $queryBus);
        Assert::assertInstanceOf(CommandBus::class, $commandBus);
        Assert::assertInstanceOf(AgentContext::class, $context);
        Assert::assertInstanceOf(ToolExceptionMapper::class, $mapper);

        return new ProbeTool($queryBus, $commandBus, $context, $mapper, $failure);
    }
}
