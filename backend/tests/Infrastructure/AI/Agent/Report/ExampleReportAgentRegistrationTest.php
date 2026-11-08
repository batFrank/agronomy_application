<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Agent\Report;

use Symfony\AI\Agent\AgentInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The generation agent of ai.yaml, as the container builds it: an agent, with no tools, and so with no
 * toolbox for StripIdentifiersFromToolboxesPass to decorate.
 */
class ExampleReportAgentRegistrationTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testTheExampleReportAgentIsRegisteredAsAnAgent(): void
    {
        $agent = self::getContainer()->get('test.ai.agent.example_report');

        self::assertInstanceOf(AgentInterface::class, $agent);
        self::assertSame('example_report', $agent->getName());
    }

    public function testItHasNoToolbox(): void
    {
        $container = self::getContainer();

        // The control: the agent that does have tools does get one, so the container does expose it.
        self::assertTrue($container->has('ai.toolbox.user_directory'));
        self::assertFalse($container->has('ai.toolbox.example_report'), 'An agent with no tools gets no toolbox.');
    }
}
