<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\AI;

use App\Application\AI\AgentAnswer;
use App\Application\AI\AgentConversation;
use App\Application\AI\AgentTurn;
use App\Application\AI\AgentTurnCollection;
use App\Application\AI\AgentTurnRole;
use App\Application\AI\AgentUnavailableException;
use App\Application\AI\InvalidAgentConversationException;
use App\Application\Query\AskAgent;
use App\Domain\Shared\Collection\CollectionException;
use App\Infrastructure\Bus\QueryBusAccessor;
use App\Infrastructure\Request\Exception\RequestAttributeExtractorException;
use App\Infrastructure\Request\RequestAttributeExtractor;
use App\Infrastructure\Response\ApiResponseContent;
use App\Infrastructure\Validator\AI\AiChatValidator;
use App\Infrastructure\Validator\ValidatorException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class PostAiChatController extends QueryBusAccessor
{
    /**
     * @throws RequestAttributeExtractorException
     * @throws ValidatorException
     * @throws InvalidAgentConversationException
     * @throws AgentUnavailableException
     */
    public function __invoke(Request $request): JsonResponse
    {
        $payload = self::payloadOf($request->getContent());

        // The validator checks the shape and the cap of turns, so it runs before any turn is built.
        AiChatValidator::validateAiChatRequest($payload);

        $answer = $this->ask(
            query: new AskAgent(
                actor: RequestAttributeExtractor::extractUser($request),
                conversation: AgentConversation::ofTurns(self::turnsOf($payload['turns'])),
            ),
        );
        \assert($answer instanceof AgentAnswer);

        return new JsonResponse(
            ApiResponseContent::createFromData(['answer' => $answer->text()])->toArray(),
            Response::HTTP_OK
        );
    }

    /**
     * The JSON as an associative array, except for one thing the array cannot say: whether `turns` was
     * a JSON array or a JSON object. Decoding with `assoc: true` turns both into a PHP array (and even
     * `{"0": ...}` into a list), so that distinction is read from a decoding that keeps it, where an
     * object is always a stdClass, and a `turns` that was an object is handed to the validator as such
     * to be rejected there.
     *
     * @return array<string, mixed>
     */
    private static function payloadOf(string $content): array
    {
        $payload = json_decode($content, true);
        if (!\is_array($payload)) {
            return [];
        }

        $decoded = json_decode($content);
        if ($decoded instanceof \stdClass && ($decoded->turns ?? null) instanceof \stdClass) {
            $payload['turns'] = $decoded->turns;
        }

        return $payload;
    }

    /**
     * Translates the wire format into the turns of the application: the strings of the client are the
     * boundary's business and do not go further in.
     *
     * @param list<array{role: string, content: string}> $turns already validated
     */
    private static function turnsOf(array $turns): AgentTurnCollection
    {
        $collection = new AgentTurnCollection();

        try {
            foreach ($turns as $turn) {
                $collection->append(AgentTurn::of('assistant' === $turn['role'] ? AgentTurnRole::Assistant : AgentTurnRole::User, $turn['content']));
            }
        } catch (CollectionException $exception) {
            // Every item is an AgentTurn: the collection only refuses another type.
            throw new \LogicException('A turn of the conversation is not an AgentTurn.', 0, $exception);
        }

        return $collection;
    }
}
