<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Agent;

use App\Application\AI\AgentUnavailableException;
use App\Application\AI\Report;
use App\Application\AI\ReportSection;
use App\Application\Security\User as SecurityUser;
use App\Domain\Security\SecurityException;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Infrastructure\AI\Agent\Report\ExampleReport;
use App\Infrastructure\AI\Agent\SymfonyAiReportGateway;
use App\Infrastructure\AI\Context\AgentContext;
use App\Infrastructure\AI\StructuredOutput\StructuredOutputDecoder;
use App\Tests\Mother\Application\Security\UserMother;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\Execution\Update\Result;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException as PlatformRuntimeException;
use Symfony\AI\Platform\Exception\ValidationException;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\ModelCatalog\FallbackModelCatalog;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Provider;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\AI\Platform\StructuredOutput\ResponseFormatFactory;
use Symfony\AI\Platform\StructuredOutput\Validator\ValidatorSubscriber;
use Symfony\AI\Platform\Test\MockModelClient;
use Symfony\AI\Platform\Test\MockResultConverter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerException;
use Symfony\Component\Validator\Validation;

/**
 * The gateway over the real agent loop and the real structured output of the package (its subscribers
 * convert and validate the text the model answers), with only the provider scripted: a platform built
 * with the SDK's own mock client, which opens no connection. The container's ScriptedPlatform cannot
 * be used here, because it replaces the whole platform and with it the structured output.
 */
#[CoversClass(SymfonyAiReportGateway::class)]
class SymfonyAiReportGatewayTest extends TestCase
{
    private const string VALID_REPORT = '{"summary":"Three people use the system.","sections":[{"title":"Roles","content":"One admin, one manager and one user."},{"title":"Dates","content":"All created in January."}]}';

    private AgentContext $context;

    /** @var list<array{payload: array<string|int, mixed>|string, options: array<string, mixed>}> */
    private array $calls = [];

    protected function setUp(): void
    {
        $this->context = new AgentContext();
        $this->calls = [];
    }

    /**
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     * @throws SecurityException
     * @throws AgentUnavailableException
     */
    public function testTheConsumerGetsTheTypedReportWithoutTouchingThePackage(): void
    {
        // Arrange
        $gateway = $this->gatewayAnswering(self::VALID_REPORT);

        // Act
        $report = $gateway->generate(UserMother::create(), 'Report on the users.');

        // Assert
        self::assertInstanceOf(Report::class, $report);
        self::assertSame('Three people use the system.', $report->summary());
        self::assertEquals(
            [ReportSection::of('Roles', 'One admin, one manager and one user.'), ReportSection::of('Dates', 'All created in January.')],
            $report->sections(),
        );
    }

    /**
     * What the model receives: the instructions as the user's message after the prompt, and the schema
     * of the DTO as `response_format`, with the descriptions and the constraints of the class.
     *
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     * @throws SecurityException
     * @throws AgentUnavailableException
     */
    public function testTheModelReceivesTheInstructionsAndTheSchemaOfTheDto(): void
    {
        // Arrange
        $gateway = $this->gatewayAnswering(self::VALID_REPORT);

        // Act
        $gateway->generate(UserMother::create(), 'Report on the users.');

        // Assert
        self::assertCount(1, $this->calls);
        $payload = $this->calls[0]['payload'];
        self::assertIsArray($payload);
        $options = $this->calls[0]['options'];
        self::assertSame('json_schema', $options['response_format']['type']);
        self::assertSame('ExampleReport', $options['response_format']['json_schema']['name']);
        $schema = $options['response_format']['json_schema']['schema'];
        self::assertSame('Executive summary, three sentences at most', $schema['properties']['summary']['description']);
        self::assertSame(['summary', 'sections'], $schema['required']);
    }

    /**
     * The constraints of the DTO are not only checked afterwards: they reach the schema the model sees.
     */
    public function testTheConstraintsOfTheDtoReachTheSchemaTheModelSees(): void
    {
        // Arrange
        $gateway = $this->gatewayAnswering(self::VALID_REPORT);

        // Act
        $gateway->generate(UserMother::create(), 'Report.');

        // Assert
        $schema = $this->calls[0]['options']['response_format']['json_schema']['schema'];
        self::assertSame(600, $schema['properties']['summary']['maxLength']);
        self::assertSame(1, $schema['properties']['sections']['minItems']);
        self::assertSame(5, $schema['properties']['sections']['maxItems']);
        $section = $schema['properties']['sections']['items'];
        self::assertSame(80, $section['properties']['title']['maxLength']);
        self::assertSame(2000, $section['properties']['content']['maxLength']);
        self::assertSame('Short title of the section', $section['properties']['title']['description']);
    }

    /**
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     * @throws SecurityException
     */
    public function testAnOutputThatIsNotJsonIsAnAgentUnavailableWithTheJsonExceptionAsTheCause(): void
    {
        // Arrange
        $gateway = $this->gatewayAnswering('Sorry, here is your report: three people.');

        // Act
        try {
            $gateway->generate(UserMother::create(), 'Report.');
            self::fail('An output that is not JSON must not become a Report.');
        } catch (AgentUnavailableException $exception) {
            // Assert
            // With the schema as an array the package decodes the text itself: what it reports for a text
            // that is not JSON is the JsonException, inside its own RuntimeException.
            self::assertInstanceOf(PlatformRuntimeException::class, $exception->getPrevious());
            $causes = $this->causesOf($exception);
            self::assertInstanceOf(\JsonException::class, $causes[array_key_last($causes)], 'The real cause must reach the end of the chain.');
        }
    }

    /**
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     * @throws SecurityException
     */
    public function testAJsonThatDoesNotFitTheSchemaIsAnAgentUnavailableWithTheSerializerExceptionAsTheCause(): void
    {
        // Arrange: a required field is missing.
        $gateway = $this->gatewayAnswering('{"sections":[{"title":"Roles","content":"x"}]}');

        // Act
        try {
            $gateway->generate(UserMother::create(), 'Report.');
            self::fail('A JSON that does not fit the schema must not become a Report.');
        } catch (AgentUnavailableException $exception) {
            // Assert
            $causes = $this->causesOf($exception);
            self::assertInstanceOf(SerializerException::class, $causes[array_key_last($causes)], 'The real cause must reach the end of the chain.');
        }
    }

    /**
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     * @throws SecurityException
     */
    public function testATypeThatDoesNotMatchIsAnAgentUnavailableWithTheSerializerExceptionAsTheCause(): void
    {
        // Arrange: the summary is a number, the sections are not a list.
        $gateway = $this->gatewayAnswering('{"summary":42,"sections":"none"}');

        // Act
        try {
            $gateway->generate(UserMother::create(), 'Report.');
            self::fail('A JSON of the wrong types must not become a Report.');
        } catch (AgentUnavailableException $exception) {
            // Assert
            $causes = $this->causesOf($exception);
            self::assertInstanceOf(SerializerException::class, $causes[array_key_last($causes)]);
        }
    }

    /**
     * Valid against the shape, but not against the constraints of the DTO: the package validates them and
     * the failure is reported the same way, with the violations reachable from the cause.
     *
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     * @throws SecurityException
     */
    public function testAnOutputThatBreaksAConstraintIsAnAgentUnavailableWithTheValidationExceptionAsTheCause(): void
    {
        // Arrange: no sections, and the DTO asks for at least one.
        $gateway = $this->gatewayAnswering('{"summary":"Nothing to say.","sections":[]}');

        // Act
        try {
            $gateway->generate(UserMother::create(), 'Report.');
            self::fail('An output that breaks a constraint must not become a Report.');
        } catch (AgentUnavailableException $exception) {
            // Assert
            $cause = $exception->getPrevious();
            self::assertInstanceOf(ValidationException::class, $cause);
            $violations = $cause->getViolations();
            self::assertInstanceOf(\Countable::class, $violations);
            self::assertCount(1, $violations);
        }
    }

    /**
     * The constraint of a nested section is validated too (`#[Assert\Valid]`), not only the root.
     *
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     * @throws SecurityException
     */
    public function testAConstraintOfANestedSectionIsValidatedToo(): void
    {
        // Arrange: a title of blanks.
        $gateway = $this->gatewayAnswering('{"summary":"Ok.","sections":[{"title":"   ","content":"x"}]}');

        // Act
        $this->expectException(AgentUnavailableException::class);

        $gateway->generate(UserMother::create(), 'Report.');
    }

    /**
     * The schema forbids more keys than it declares (`additionalProperties: false`), but the package does
     * not enforce it when it deserializes: the decoder does. The two reproductions of the Reviewer.
     *
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     * @throws SecurityException
     */
    #[DataProvider('outputsWithUndeclaredKeys')]
    public function testAnOutputWithAKeyTheSchemaDoesNotDeclareIsAnAgentUnavailable(string $output, string $key): void
    {
        // Arrange
        $gateway = $this->gatewayAnswering($output);

        // Act
        try {
            $gateway->generate(UserMother::create(), 'Report.');
            self::fail('An output with undeclared keys must not become a Report.');
        } catch (AgentUnavailableException $exception) {
            // Assert
            $cause = $exception->getPrevious();
            self::assertInstanceOf(PlatformRuntimeException::class, $cause);
            self::assertStringContainsString($key, $cause->getMessage());
        }
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function outputsWithUndeclaredKeys(): \Generator
    {
        yield 'a key too many in the root' => [
            '{"summary":"Ok.","sections":[{"title":"Roles","content":"x"}],"confidence":0.9}',
            'confidence',
        ];
        yield 'a key too many in a section' => [
            '{"summary":"Ok.","sections":[{"title":"Roles","content":"x"},{"title":"Dates","content":"y","author":"model"}]}',
            'sections[1].author',
        ];
        // Reproduced before the fix: the serializer kept the keys [1, 5] and the Report was not a list.
        yield 'sections as an object with keys that are not consecutive' => [
            '{"summary":"Ok.","sections":{"1":{"title":"A","content":"x"},"5":{"title":"B","content":"y"}}}',
            '"sections" is not a list',
        ];
    }

    /**
     * Valid JSON that is not an object. Measured: the package cannot even build its result from it.
     *
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     * @throws SecurityException
     */
    #[DataProvider('outputsThatAreNotAnObject')]
    public function testAJsonThatIsNotAnObjectIsAnAgentUnavailable(string $output): void
    {
        // Arrange
        $gateway = $this->gatewayAnswering($output);

        // Act
        $this->expectException(AgentUnavailableException::class);

        $gateway->generate(UserMother::create(), 'Report.');
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function outputsThatAreNotAnObject(): \Generator
    {
        yield 'a number' => ['42'];
        yield 'a string' => ['"report"'];
        yield 'null' => ['null'];
        yield 'a list' => ['[1,2]'];
    }

    /**
     * A JSON list is an array in PHP too: the cause in the log must say it is not an object, not list the
     * keys 0 and 1 as undeclared.
     *
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     * @throws SecurityException
     */
    public function testAJsonListIsReportedAsNotAnObject(): void
    {
        // Arrange
        $gateway = $this->gatewayAnswering('[1,2]');

        // Act
        try {
            $gateway->generate(UserMother::create(), 'Report.');
            self::fail('A list must not become a Report.');
        } catch (AgentUnavailableException $exception) {
            // Assert
            self::assertSame('The structured output is not a JSON object.', $exception->getPrevious()?->getMessage());
        }
    }

    /**
     * Reading the output runs the whole lazy execution, so a TypeError from anywhere else in it is a bug
     * and must not be taken for the model answering badly. Here it comes from the client, before any
     * result is built.
     *
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     * @throws SecurityException
     */
    public function testATypeErrorThatIsNotFromBuildingTheResultPropagatesUntouched(): void
    {
        // Arrange
        $gateway = $this->gatewayThrowing(new \TypeError('a bug in the client'));

        // Act
        try {
            $gateway->generate(UserMother::create(), 'Report.');
            self::fail('A bug must not become an AgentUnavailableException.');
        } catch (\Throwable $error) {
            // Assert
            self::assertInstanceOf(\TypeError::class, $error);
            self::assertSame('a bug in the client', $error->getMessage());
            self::assertNull($this->actorOrNull(), 'The context must be left even when a bug propagates.');
        }
    }

    /**
     * What the provider is asked for is the schema of the DTO, strict and closed: the same the package
     * built when the class was passed as `response_format`.
     */
    public function testTheSchemaTheModelReceivesIsStrictAndClosed(): void
    {
        // Arrange
        $gateway = $this->gatewayAnswering(self::VALID_REPORT);

        // Act
        $gateway->generate(UserMother::create(), 'Report.');

        // Assert
        $format = $this->calls[0]['options']['response_format'];
        self::assertSame($format, (new ResponseFormatFactory())->create(ExampleReport::class));
        self::assertTrue($format['json_schema']['strict']);
        $schema = $format['json_schema']['schema'];
        self::assertFalse($schema['additionalProperties']);
        self::assertFalse($schema['properties']['sections']['items']['additionalProperties']);
    }

    /**
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     * @throws SecurityException
     */
    public function testAProviderFailureIsAnAgentUnavailableAndTheContextIsLeft(): void
    {
        // Arrange
        $gateway = $this->gatewayThrowing(new RateLimitExceededException());

        // Act
        try {
            $gateway->generate(UserMother::create(), 'Report.');
            self::fail('The provider failed.');
        } catch (AgentUnavailableException $exception) {
            // Assert
            self::assertInstanceOf(RateLimitExceededException::class, $exception->getPrevious());
            self::assertNull($this->actorOrNull());
        }
    }

    /**
     * The contract of the funnel, with an agent whose execution is lazy and records what it sees when it
     * is consumed: the actor is entered before, available while the output is resolved, and left after.
     *
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     * @throws SecurityException
     * @throws AgentUnavailableException
     */
    public function testTheActorIsAvailableWhileTheLazyExecutionIsConsumedAndLeftAfterwards(): void
    {
        // Arrange
        $actor = UserMother::create();
        $seenWhileRunning = null;
        $execution = new Execution(function () use (&$seenWhileRunning): \Generator {
            $seenWhileRunning = $this->actorOrNull();

            yield new Result(new ObjectResult(['summary' => 'Done.', 'sections' => [['title' => 'Only', 'content' => 'One.']]]));
        });
        $agent = new class($execution) implements AgentInterface {
            public function __construct(private readonly Execution $execution)
            {
            }

            public function call(MessageBag|\Symfony\AI\Platform\Message\UserMessage|string $input, array $options = []): Execution
            {
                return $this->execution;
            }

            public function getName(): string
            {
                return 'lazy-double';
            }
        };

        // Act
        $report = $this->gatewayOver($agent)->generate($actor, 'Report.');

        // Assert
        self::assertSame($actor, $seenWhileRunning, 'The actor must be available while the output is resolved.');
        self::assertNull($this->actorOrNull(), 'The context must be left when the output is done.');
        self::assertSame('Done.', $report->summary());
    }

    /**
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     * @throws SecurityException
     * @throws AgentUnavailableException
     */
    public function testASecondCallWhileOneIsRunningFailsAndDoesNotWipeTheActorOfTheFirst(): void
    {
        // Arrange
        $first = UserMother::create();
        $this->context->enterAs($first);

        // Act
        try {
            $this->gatewayAnswering(self::VALID_REPORT)->generate(UserMother::create(), 'Report.');
            self::fail('A second actor must be refused.');
        } catch (\LogicException) {
            // Assert
            self::assertSame($first, $this->actorOrNull(), 'leave() must not run when enterAs() refused.');
        }
    }

    /**
     * @param string $text what the model answers, as the text of its message
     */
    private function gatewayAnswering(string $text): SymfonyAiReportGateway
    {
        return $this->gatewayOver($this->realAgentWith(fn (): string => $text));
    }

    private function gatewayThrowing(\Throwable $failure): SymfonyAiReportGateway
    {
        return $this->gatewayOver($this->realAgentWith(static function () use ($failure): never {
            throw $failure;
        }));
    }

    private function gatewayOver(AgentInterface $agent): SymfonyAiReportGateway
    {
        return new SymfonyAiReportGateway($agent, $this->context, new StructuredOutputDecoder(Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()));
    }

    /**
     * @param \Closure(): string $script
     */
    private function realAgentWith(\Closure $script): Agent
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new PlatformSubscriber());
        $dispatcher->addSubscriber(new ValidatorSubscriber(Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()));

        $client = new MockModelClient(function (\Symfony\AI\Platform\Model $model, array|string $payload, array $options) use ($script): string {
            $this->calls[] = ['payload' => $payload, 'options' => $options];

            return $script();
        });
        $provider = new Provider('scripted', [$client], [new MockResultConverter()], new FallbackModelCatalog(), null, $dispatcher);

        return new Agent(new Platform([$provider], eventDispatcher: $dispatcher), 'scripted-model');
    }

    /**
     * @return non-empty-list<\Throwable>
     */
    private function causesOf(\Throwable $exception): array
    {
        $chain = [];
        for ($current = $exception->getPrevious(); null !== $current; $current = $current->getPrevious()) {
            $chain[] = $current;
        }
        if ([] === $chain) {
            self::fail('The real cause must travel in previous.');
        }

        return $chain;
    }

    private function actorOrNull(): ?SecurityUser
    {
        try {
            return $this->context->actor();
        } catch (\LogicException) {
            return null;
        }
    }
}
