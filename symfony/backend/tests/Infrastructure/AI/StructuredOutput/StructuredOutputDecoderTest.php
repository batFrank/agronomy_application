<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\StructuredOutput;

use App\Infrastructure\AI\StructuredOutput\StructuredOutputDecoder;
use App\Tests\Infrastructure\AI\StructuredOutput\Fixture\Audit;
use App\Tests\Infrastructure\AI\StructuredOutput\Fixture\Priority;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ValidationException;
use Symfony\Component\Validator\Validation;

/**
 * The decoder is generic, so it is tested with a class that is not the example: a date, an enum, a
 * nullable object and a list of objects that hold a list of objects.
 */
#[CoversClass(StructuredOutputDecoder::class)]
class StructuredOutputDecoderTest extends TestCase
{
    private StructuredOutputDecoder $decoder;

    protected function setUp(): void
    {
        $this->decoder = new StructuredOutputDecoder(Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator());
    }

    public function testItDecodesWhatTheSchemaDescribesIncludingADateAnEnumAndNestedObjects(): void
    {
        // Act
        $audit = $this->decoder->decode(self::valid(), Audit::class);

        // Assert
        self::assertEquals(new \DateTimeImmutable('2026-11-02T10:00:00+00:00'), $audit->performedAt);
        self::assertSame('Ana', $audit->lead->name);
        self::assertNull($audit->backup);
        self::assertSame(Priority::High, $audit->findings[0]->priority);
        self::assertSame('Luis', $audit->findings[0]->reviewers[0]->name);
    }

    public function testTheSchemaItAsksForIsTheOneOfThePackageForTheClass(): void
    {
        // Act
        $format = $this->decoder->responseFormat(Audit::class);

        // Assert
        self::assertSame('json_schema', $format['type']);
        self::assertSame('Audit', $format['json_schema']['name']);
        self::assertTrue($format['json_schema']['strict']);
        self::assertFalse($format['json_schema']['schema']['additionalProperties']);
    }

    /**
     * @param array<string, mixed> $output
     */
    #[DataProvider('outputsThatBreakTheSchema')]
    public function testItRejectsWhatBreaksTheSchemaAtAnyDepth(array $output, string $problem): void
    {
        // Act
        try {
            $this->decoder->decode($output, Audit::class);
            self::fail('The output breaks the schema and must be rejected.');
        } catch (RuntimeException $exception) {
            // Assert
            self::assertStringContainsString($problem, $exception->getMessage());
        }
    }

    /**
     * @return \Generator<string, array{array<string, mixed>, string}>
     */
    public static function outputsThatBreakTheSchema(): \Generator
    {
        yield 'a key too many in the root' => [[...self::valid(), 'extra' => 1], '"extra" is not declared'];
        yield 'a key too many in an object' => [[...self::valid(), 'lead' => ['name' => 'Ana', 'note' => null, 'age' => 3]], '"lead.age" is not declared'];
        yield 'a key too many in a nullable object that is there' => [[...self::valid(), 'backup' => ['name' => 'Eva', 'note' => null, 'age' => 3]], '"backup.age" is not declared'];
        yield 'a key too many in an item of a list' => [
            [...self::valid(), 'findings' => [[...self::finding(), 'severity' => 9]]],
            '"findings[0].severity" is not declared',
        ];
        yield 'a key too many three levels down' => [
            [...self::valid(), 'findings' => [[...self::finding(), 'reviewers' => [['name' => 'Luis', 'note' => null], ['name' => 'Eva', 'note' => null, 'mail' => 'x']]]]],
            '"findings[0].reviewers[1].mail" is not declared',
        ];
        yield 'a list that is an object with keys that are not consecutive' => [
            [...self::valid(), 'findings' => [1 => self::finding(), 5 => self::finding()]],
            '"findings" is not a list',
        ];
        yield 'a list nested in another that is not a list' => [
            [...self::valid(), 'findings' => [[...self::finding(), 'reviewers' => ['a' => ['name' => 'Luis', 'note' => null]]]]],
            '"findings[0].reviewers" is not a list',
        ];
    }

    public function testAnOutputThatIsAListOrNotAnArrayIsReportedAsNotAnObject(): void
    {
        foreach ([[1, 2], 42, 'x', null] as $output) {
            try {
                $this->decoder->decode($output, Audit::class);
                self::fail('Not an object.');
            } catch (RuntimeException $exception) {
                self::assertSame('The structured output is not a JSON object.', $exception->getMessage());
            }
        }
    }

    public function testWhatDoesNotMatchTheTypesIsLeftToTheDenormalizerWithItsErrorAsTheCause(): void
    {
        // Act
        try {
            $this->decoder->decode([...self::valid(), 'performedAt' => 'not a date'], Audit::class);
            self::fail('A date that is not a date.');
        } catch (RuntimeException $exception) {
            // Assert
            self::assertInstanceOf(\Throwable::class, $exception->getPrevious());
        }
    }

    public function testAConstraintOfANestedObjectIsValidated(): void
    {
        // Act
        try {
            $this->decoder->decode([...self::valid(), 'lead' => ['name' => '   ', 'note' => null]], Audit::class);
            self::fail('A blank name.');
        } catch (ValidationException $exception) {
            // Assert
            $violations = $exception->getViolations();
            self::assertInstanceOf(\Countable::class, $violations);
            self::assertCount(1, $violations);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function valid(): array
    {
        return [
            'performedAt' => '2026-11-02T10:00:00+00:00',
            'lead' => ['name' => 'Ana', 'note' => null],
            'backup' => null,
            'findings' => [self::finding()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function finding(): array
    {
        return ['text' => 'Something.', 'priority' => 'high', 'reviewers' => [['name' => 'Luis', 'note' => null]]];
    }
}
