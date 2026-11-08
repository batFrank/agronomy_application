<?php

declare(strict_types=1);

namespace App\Tests\Application\AI;

use App\Application\AI\AgentUnavailableException;
use App\Domain\Shared\Exception\BadGatewayException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AgentUnavailableException::class)]
class AgentUnavailableExceptionTest extends TestCase
{
    private const string GENERIC_ANSWER_MESSAGE = 'The assistant did not give a usable answer.';

    public function testAnEmptyAnswerSaysSoInTheCauseAndIsGenericForTheClient(): void
    {
        $exception = AgentUnavailableException::emptyAnswer();

        self::assertSame(self::GENERIC_ANSWER_MESSAGE, $exception->getMessage());
        self::assertInstanceOf(\UnexpectedValueException::class, $exception->getPrevious());
        self::assertSame('The agent answered with an empty text.', $exception->getPrevious()->getMessage());
    }

    /**
     * @return \Generator<string, array{int|float|bool|array<mixed>|object|null, string}>
     */
    public static function nonTextContentProvider(): \Generator
    {
        yield 'object' => [new \stdClass(), 'stdClass'];
        yield 'array' => [['a' => 1], 'array'];
        yield 'null' => [null, 'null'];
        yield 'int' => [42, 'int'];
        yield 'bool' => [true, 'bool'];
    }

    /**
     * @param int|float|bool|array<mixed>|object|null $content
     */
    #[DataProvider('nonTextContentProvider')]
    public function testANonTextAnswerNamesTheTypeInTheCauseAndNotInTheMessage(int|float|bool|array|object|null $content, string $type): void
    {
        $exception = AgentUnavailableException::nonTextAnswer($content);

        self::assertSame(self::GENERIC_ANSWER_MESSAGE, $exception->getMessage());
        self::assertInstanceOf(\UnexpectedValueException::class, $exception->getPrevious());
        self::assertSame(\sprintf('The agent answered with %s instead of text.', $type), $exception->getPrevious()->getMessage());
        self::assertStringNotContainsString($type, $exception->getMessage());
    }

    /**
     * What made the old factory a trap: a string could reach it and leave a false cause in the log.
     * Now the type refuses it before a cause is built.
     */
    public function testNonTextAnswerRefusesAString(): void
    {
        $this->expectException(\TypeError::class);

        $exception = AgentUnavailableException::nonTextAnswer($this->aString());

        self::fail(\sprintf('A string must not be accepted, got: %s', $exception->getMessage()));
    }

    public function testTheProviderFailureKeepsTheOriginalAsPrevious(): void
    {
        $failure = new \RuntimeException('Could not resolve host: api.provider-secret.test');

        $exception = AgentUnavailableException::fromProviderFailure($failure);

        self::assertSame('The assistant is not available at the moment.', $exception->getMessage());
        self::assertSame($failure, $exception->getPrevious());
        self::assertInstanceOf(BadGatewayException::class, $exception);
    }

    /**
     * Hides the type from the static analysis, which would reject the call on its own.
     */
    private function aString(): mixed
    {
        return 'a response that does not fit a format';
    }
}
