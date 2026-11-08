<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Toolbox\Double;

use App\Domain\Bus\Query;

final readonly class ProbeQuery implements Query
{
    public function __construct(
        public ?\Throwable $failure,
        public string $value,
    ) {
    }
}
