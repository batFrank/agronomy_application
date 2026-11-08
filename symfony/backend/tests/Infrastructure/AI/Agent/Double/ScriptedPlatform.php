<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Agent\Double;

use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Test\InMemoryPlatform;

/**
 * Stands in for the real provider (services_test.yaml decorates `ai.platform.openrouter` with it), so
 * no test calls the network. Each test scripts the model: the closure receives the messages the
 * agent sends and returns what the model would answer.
 */
final class ScriptedPlatform implements PlatformInterface
{
    /** @var list<array<mixed>|string|object> */
    private array $inputs = [];

    /** @var \Closure(array<mixed>|string|object): (ResultInterface|string) */
    private \Closure $script;

    private readonly InMemoryPlatform $inner;

    /**
     * @param PlatformInterface $decorated the real platform: received only so the container keeps it
     *                                     (and its env vars) referenced. It is deliberately neither
     *                                     stored nor exposed, so nothing reaches the provider through
     *                                     this service
     */
    public function __construct(// @phpstan-ignore constructor.unusedParameter
        PlatformInterface $decorated,
    ) {
        $this->script = static fn (): string => 'No answer was scripted.';
        $this->inner = new InMemoryPlatform(function (Model $model, array|string|object $input) {
            // A snapshot: the agent loop keeps adding messages to the very bag it sent, and what the model
            // received in this call must not change afterwards.
            $this->inputs[] = \is_object($input) ? clone $input : $input;

            return ($this->script)($input);
        });
    }

    /**
     * @param \Closure(array<mixed>|string|object): (ResultInterface|string) $script
     */
    public function script(\Closure $script): void
    {
        $this->script = $script;
        $this->inputs = [];
    }

    /**
     * What the agent sent to the model, one entry per call.
     *
     * @return list<array<mixed>|string|object>
     */
    public function inputs(): array
    {
        return $this->inputs;
    }

    public function invoke(string|Model $model, array|string|object $input, array $options = []): DeferredResult
    {
        return $this->inner->invoke($model, $input, $options);
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        return $this->inner->getModelCatalog();
    }
}
