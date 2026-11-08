<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Toolbox\Double;

use App\Domain\Bus\CommandBus;
use App\Domain\Bus\QueryBus;
use App\Infrastructure\AI\Context\AgentContext;
use App\Infrastructure\AI\Toolbox\AgentTool;
use App\Infrastructure\AI\Toolbox\ToolExceptionMapper;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\AiBundle\Security\Attribute\IsGrantedTool;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

#[AsTool('probe', 'Probe tool that answers with the city it receives.')]
#[AsTool('probe_payload', 'Probe tool that receives a payload object.', method: 'withPayload')]
#[AsTool('probe_guarded', 'Probe tool that requires ROLE_ADMIN.', method: 'guarded')]
#[AsTool('probe_actor', 'Probe tool that uses the actor from the context.', method: 'actor')]
final readonly class ProbeTool extends AgentTool
{
    public function __construct(
        QueryBus $queryBus,
        CommandBus $commandBus,
        AgentContext $context,
        ToolExceptionMapper $exceptionMapper,
        private ?\Throwable $failure = null,
    ) {
        parent::__construct($queryBus, $commandBus, $context, $exceptionMapper);
    }

    public function __invoke(#[Schema(minLength: 5)] string $city): string
    {
        return $this->ask(new ProbeQuery($this->failure, $city));
    }

    public function withPayload(ProbePayload $payload): string
    {
        return $this->ask(new ProbeQuery($this->failure, $payload->secretField));
    }

    #[IsGrantedTool('ROLE_ADMIN')]
    public function guarded(string $city): string
    {
        return $this->ask(new ProbeQuery($this->failure, $city));
    }

    public function actor(): string
    {
        return $this->ask(new ProbeQuery($this->failure, $this->context->actor()->getUserIdentifier()));
    }
}
