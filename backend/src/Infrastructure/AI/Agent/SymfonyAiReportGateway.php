<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Agent;

use App\Application\AI\AgentUnavailableException;
use App\Application\AI\Report;
use App\Application\AI\ReportGateway;
use App\Application\AI\ReportSection;
use App\Application\Security\User as SecurityUser;
use App\Infrastructure\AI\Agent\Report\ExampleReport;
use App\Infrastructure\AI\Agent\Report\ExampleReportSection;
use App\Infrastructure\AI\Context\AgentContext;
use App\Infrastructure\AI\StructuredOutput\StructuredOutputDecoder;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Exception\ExceptionInterface as AgentException;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Platform\Exception\ExceptionInterface as PlatformException;
use Symfony\AI\Platform\Exception\RuntimeException as PlatformRuntimeException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;

/**
 * Adapter of ReportGateway over symfony/ai-agent: the generation form, an agent with no tools that is
 * asked for a structured output. It goes through the same funnel as SymfonyAiAgentGateway (the
 * AgentContext is entered before running the agent and left in a finally) even though this agent has
 * no tools that read the actor: a fork that adds one finds the path already with its context.
 *
 * It wires the `example_report` agent of ai.yaml; a fork points it at its own and replaces
 * ExampleReport with the shape of its output.
 *
 * What the consumer gets is a Report or an AgentUnavailableException (502, generic message). The
 * output that does not have the shape asked for needs no exception of its own, it is always one of the
 * Platform family caught below: a RuntimeException when the text is not JSON (the JsonException in
 * `previous`), when it is JSON but not an object, when it has keys the DTO does not declare, or when
 * it does not deserialize into the DTO (the serializer's exception in `previous`), and a
 * ValidationException when it breaks a constraint of it.
 * A model that does not support structured output is not detected here: asking for the schema as an
 * array skips the package's OUTPUT_STRUCTURED check (MissingModelSupportException is not thrown), so
 * it is the provider that rejects the request, and that failure ends as an AgentUnavailableException
 * like any other, although it is a mistake of configuration.
 */
final readonly class SymfonyAiReportGateway implements ReportGateway
{
    public function __construct(
        #[Autowire(service: 'ai.agent.example_report')]
        private AgentInterface $agent,
        private AgentContext $context,
        private StructuredOutputDecoder $decoder,
    ) {
    }

    #[\Override]
    public function generate(SecurityUser $actor, string $instructions): Report
    {
        // Outside the try on purpose: if the context refuses a second actor, the leave() below must
        // not run, or it would wipe the actor of the call that is already running.
        $this->context->enterAs($actor);

        try {
            // The schema is asked for as an array, built here with the package's own factory (the one it
            // would use for a class, so the provider sees the same schema), and not as the class: with a
            // class the package deserializes on its own and silently drops the keys the DTO does not
            // declare, which the schema forbids. As an array it hands the decoded answer back untouched
            // and the decoder checks it.
            // call() is lazy, and so is the conversion of the output: both happen when it is read.
            // Reading and decoding it here, inside the scope, keeps the actor available meanwhile.
            $execution = $this->agent->call(
                new MessageBag(Message::ofUser($instructions)),
                ['response_format' => $this->decoder->responseFormat(ExampleReport::class)],
            );
            $report = $this->decoder->decode($this->outputOf($execution), ExampleReport::class);
        } catch (AgentException|PlatformException|HttpClientException $exception) {
            throw AgentUnavailableException::fromProviderFailure($exception);
        } finally {
            $this->context->leave();
        }

        return Report::of(
            $report->summary,
            array_map(
                static fn (ExampleReportSection $section): ReportSection => ReportSection::of($section->title, $section->content),
                $report->sections,
            ),
        );
    }

    /**
     * What the model answered, decoded. Without a class the package builds its ObjectResult straight
     * from the decoded JSON, and a JSON that is not an object or a list (a number, a string, null) makes
     * that a TypeError instead of an exception of its own family: it is rewrapped here so that it ends as
     * the same AgentUnavailableException as any other output that does not have the shape asked for.
     *
     * Only that TypeError: reading the output runs the whole lazy execution (the processors, the call to
     * the platform, the subscribers), and a TypeError from anywhere else in there is a bug of this
     * application or of the SDK that must propagate untouched, as SymfonyAiAgentGateway promises. The
     * package offers no way to read the text without it building the ObjectResult (its subscriber wraps
     * the result whenever `response_format` is set), so the TypeError is recognized by where it was
     * thrown: the first frame of its trace is the constructor of ObjectResult.
     */
    private function outputOf(Execution $execution): mixed
    {
        try {
            return $execution->asObject();
        } catch (\TypeError $error) {
            $thrownAt = $error->getTrace()[0] ?? null;
            if (null === $thrownAt || ObjectResult::class !== ($thrownAt['class'] ?? null) || '__construct' !== $thrownAt['function']) {
                throw $error;
            }

            throw new PlatformRuntimeException('The structured output is not a JSON object.', previous: $error);
        }
    }
}
