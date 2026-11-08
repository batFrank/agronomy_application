<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Context;

use App\Infrastructure\AI\Context\AgentContext;
use App\Tests\Mother\Application\Security\UserMother;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversNothing]
class AgentContextContainerTest extends KernelTestCase
{
    /**
     * @throws \App\Domain\Shared\Uuid\InvalidUuidException
     * @throws \App\Domain\Shared\Email\InvalidEmailException
     * @throws \App\Domain\Security\SecurityException
     */
    public function testItIsOneSharedInstanceAcrossTheContainer(): void
    {
        // Arrange
        self::bootKernel();
        $actor = UserMother::create();
        $byClass = self::getContainer()->get(AgentContext::class);
        $byAlias = self::getContainer()->get('test.agent_context');
        self::assertInstanceOf(AgentContext::class, $byClass);
        self::assertInstanceOf(AgentContext::class, $byAlias);

        // Act
        $byClass->enterAs($actor);

        // Assert
        self::assertSame($byClass, $byAlias);
        self::assertSame($actor, $byAlias->actor());

        $byAlias->leave();
    }
}
