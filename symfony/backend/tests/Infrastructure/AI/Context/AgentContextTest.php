<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Context;

use App\Infrastructure\AI\Context\AgentContext;
use App\Tests\Mother\Application\Security\UserMother;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AgentContext::class)]
class AgentContextTest extends TestCase
{
    /**
     * @throws \App\Domain\Shared\Uuid\InvalidUuidException
     * @throws \App\Domain\Shared\Email\InvalidEmailException
     * @throws \App\Domain\Security\SecurityException
     */
    #[DataProvider('provideActorThrowsWhenNoActorIsInsideCases')]
    public function testActorThrowsWhenNoActorIsInside(\Closure $arrange): void
    {
        // Arrange
        $context = new AgentContext();
        $arrange($context);

        // Assert
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('AgentContext has no actor: the call was not started properly.');

        // Act
        $context->actor();
    }

    /**
     * @return iterable<string, array{\Closure(AgentContext): void}>
     */
    public static function provideActorThrowsWhenNoActorIsInsideCases(): iterable
    {
        yield 'never entered' => [
            static function (AgentContext $context): void {
            },
        ];

        yield 'entered and left' => [
            static function (AgentContext $context): void {
                $context->enterAs(UserMother::create());
                $context->leave();
            },
        ];
    }

    /**
     * @throws \App\Domain\Shared\Uuid\InvalidUuidException
     * @throws \App\Domain\Shared\Email\InvalidEmailException
     * @throws \App\Domain\Security\SecurityException
     */
    public function testEnterAsThrowsWhenAnActorIsAlreadyInside(): void
    {
        // Arrange
        $context = new AgentContext();
        $context->enterAs(UserMother::create());
        $secondActor = UserMother::create();

        // Assert
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('AgentContext already has an actor: the previous call did not leave().');

        // Act
        $context->enterAs($secondActor);
    }

    /**
     * @throws \App\Domain\Shared\Uuid\InvalidUuidException
     * @throws \App\Domain\Shared\Email\InvalidEmailException
     * @throws \App\Domain\Security\SecurityException
     */
    public function testLeaveWithoutActorDoesNothing(): void
    {
        // Arrange
        $context = new AgentContext();
        $actor = UserMother::create();

        // Act
        $context->leave();
        $context->leave();
        $context->enterAs($actor);

        // Assert
        self::assertSame($actor, $context->actor());
    }

    /**
     * @throws \App\Domain\Shared\Uuid\InvalidUuidException
     * @throws \App\Domain\Shared\Email\InvalidEmailException
     * @throws \App\Domain\Security\SecurityException
     */
    public function testActorReturnsTheEnteredActor(): void
    {
        // Arrange
        $context = new AgentContext();
        $actor = UserMother::create();

        // Act
        $context->enterAs($actor);

        // Assert
        self::assertSame($actor, $context->actor());
    }

    /**
     * @throws \App\Domain\Shared\Uuid\InvalidUuidException
     * @throws \App\Domain\Shared\Email\InvalidEmailException
     * @throws \App\Domain\Security\SecurityException
     */
    public function testActorDoesNotLeakIntoTheNextExecution(): void
    {
        // Arrange
        $context = new AgentContext();
        $previousActor = UserMother::create();
        $nextActor = UserMother::create();
        $context->enterAs($previousActor);
        $context->leave();

        // Act
        $context->enterAs($nextActor);

        // Assert
        self::assertSame($nextActor, $context->actor());
        self::assertNotSame($previousActor, $context->actor());
    }
}
