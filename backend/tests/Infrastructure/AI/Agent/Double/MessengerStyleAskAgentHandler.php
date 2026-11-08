<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Agent\Double;

use App\Application\AI\AgentAnswer;
use App\Application\AI\AgentConversation;
use App\Application\Query\AskAgent;
use App\Application\Security\User as SecurityUser;
use App\Domain\Bus\QueryBus;

/**
 * What a Messenger handler (or a command, or a controller) does to ask the agent: it knows the query
 * bus and nothing else. It does not populate the AgentContext, does not know the gateway and does
 * not know symfony/ai-agent. The same call works from any of them without touching the port.
 */
final readonly class MessengerStyleAskAgentHandler
{
    public function __construct(private QueryBus $queryBus)
    {
    }

    public function __invoke(SecurityUser $actor, string $question): string
    {
        $answer = $this->queryBus->ask(new AskAgent($actor, AgentConversation::ofQuestion($question)));
        \assert($answer instanceof AgentAnswer);

        return $answer->text();
    }
}
