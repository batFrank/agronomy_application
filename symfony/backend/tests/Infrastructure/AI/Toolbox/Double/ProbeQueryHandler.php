<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Toolbox\Double;

final readonly class ProbeQueryHandler
{
    public function handle(ProbeQuery $query): string
    {
        if (null !== $query->failure) {
            throw $query->failure;
        }

        return 'handled:' . $query->value;
    }
}
