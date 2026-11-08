<?php

declare(strict_types=1);

namespace App\Tests\Application\AI;

use App\Application\AI\AgentTurn;
use App\Application\AI\AgentTurnRole;
use App\Application\AI\BlankAgentTurnException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AgentTurn::class)]
#[CoversClass(BlankAgentTurnException::class)]
class AgentTurnTest extends TestCase
{
    /**
     * @throws BlankAgentTurnException
     */
    public function testKeepsTheRoleAndTheTextAsGiven(): void
    {
        $turn = AgentTurn::of(AgentTurnRole::Assistant, '  Three people.  ');

        self::assertSame(AgentTurnRole::Assistant, $turn->role());
        self::assertSame('  Three people.  ', $turn->text());
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function blankTextProvider(): \Generator
    {
        yield 'empty' => [''];
        yield 'spaces' => ['   '];
        yield 'whitespace of every kind' => ["\t \n\r "];
    }

    #[DataProvider('blankTextProvider')]
    public function testARoleWithABlankTextCannotBeATurn(string $text): void
    {
        $this->expectException(BlankAgentTurnException::class);

        AgentTurn::of(AgentTurnRole::User, $text);
    }

    /**
     * What the promise of the docblock rests on: there is no way to build a turn that skips of().
     */
    public function testTheConstructorIsClosed(): void
    {
        $constructor = (new \ReflectionClass(AgentTurn::class))->getConstructor();

        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate());
    }
}
