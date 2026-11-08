<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Agent;

use App\Application\AI\AgentAnswer;
use App\Application\AI\AgentConversation;
use App\Application\AI\AgentGateway;
use App\Application\AI\AgentTurn;
use App\Application\AI\AgentTurnRole;
use App\Application\AI\AgentUnavailableException;
use App\Application\Security\User as SecurityUser;
use App\Infrastructure\AI\Context\AgentContext;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Exception\ExceptionInterface as AgentException;
use Symfony\AI\Platform\Exception\ExceptionInterface as PlatformException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;

/**
 * Adapter of AgentGateway over symfony/ai-agent. It is the funnel: it enters the AgentContext
 * before running the agent and leaves it in a finally, so neither a controller nor a Messenger
 * handler can forget to.
 *
 * It wires the `user_directory` agent of ai.yaml; a fork points it at its own.
 *
 * What it reports as AgentUnavailableException (502, generic message): whatever implements the
 * interface of one of three families, which can fail while the provider is called. The SDK's own
 * (platform and agent loop) and the HTTP client's, whose parent interface covers the seven kinds
 * (client, decoding, http, redirection, server, timeout, transport): the SDK does not wrap the
 * latter, so a DNS failure or a cut body reaches this class as it is. An answer that is not text, or
 * that is empty, is reported the same way. The real cause goes in `previous`.
 *
 * A known limit, declared and not a defect: the catch is by interface, so it also converts what is a
 * mistake of configuration or of use, not a failure of the provider. Agent\Exception\LogicException,
 * Agent\Exception\InvalidArgumentException, Platform\Exception\LogicException and
 * Platform\Exception\ModelNotFoundException implement the interfaces caught here and end up as an
 * AgentUnavailableException, and so does the InvalidArgumentException of symfony/http-client (it
 * implements TransportExceptionInterface). The same goes for the provider rejecting the content of
 * the conversation, which is the client's and not the provider being down (#127). Telling them apart would
 * mean listing concrete classes of the vendor, which breaks as soon as the SDK adds one, and the effect
 * would be the same, a 502. Only what implements none of those interfaces (a bug of this application)
 * propagates untouched.
 */
final readonly class SymfonyAiAgentGateway implements AgentGateway
{
    public function __construct(
        #[Autowire(service: 'ai.agent.user_directory')]
        private AgentInterface $agent,
        private AgentContext $context,
    ) {
    }

    #[\Override]
    public function ask(SecurityUser $actor, AgentConversation $conversation): AgentAnswer
    {
        // Outside the try on purpose: if the context refuses a second actor, the leave() below must
        // not run, or it would wipe the actor of the call that is already running.
        $this->context->enterAs($actor);

        try {
            // call() is lazy: it runs the agent, tools included, when the content is read. Reading it
            // here, inside the scope, is what keeps the actor available to the tools.
            $content = $this->agent->call($this->toMessageBag($conversation))->getContent();
        } catch (AgentException|PlatformException|HttpClientException $exception) {
            throw AgentUnavailableException::fromProviderFailure($exception);
        } finally {
            $this->context->leave();
        }

        if (!\is_string($content)) {
            throw AgentUnavailableException::nonTextAnswer($content);
        }

        // An empty answer (or only whitespace) is as unusable as one that is not text: the person
        // would get nothing, and nothing says the agent failed.
        if ('' === trim($content)) {
            throw AgentUnavailableException::emptyAnswer();
        }

        return AgentAnswer::ofText($content);
    }

    /**
     * The turns of the conversation as the SDK's messages. There is no system message here: the agent
     * adds its own prompt (ai.yaml), and the client cannot send one, because the conversation does not
     * have that role.
     */
    private function toMessageBag(AgentConversation $conversation): MessageBag
    {
        return new MessageBag(...array_map(
            static fn (AgentTurn $turn) => match ($turn->role()) {
                AgentTurnRole::User => Message::ofUser($turn->text()),
                AgentTurnRole::Assistant => Message::ofAssistant($turn->text()),
            },
            $conversation->turns()->all(),
        ));
    }
}
