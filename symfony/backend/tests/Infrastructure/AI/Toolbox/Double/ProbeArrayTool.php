<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Toolbox\Double;

use App\Domain\User\User;
use App\Infrastructure\Transformer\User\UserTransformer;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * Tool that returns the transformer output of a user, as a tool returning an array would.
 */
#[AsTool('probe_array', 'Probe tool that answers with the transformed user.')]
final readonly class ProbeArrayTool
{
    public function __construct(
        private User $user,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        return UserTransformer::toArray($this->user);
    }
}
