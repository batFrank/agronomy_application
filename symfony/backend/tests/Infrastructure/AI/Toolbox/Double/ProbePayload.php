<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Toolbox\Double;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ProbePayload
{
    public function __construct(
        #[Assert\Length(min: 5)]
        public string $secretField = '',
    ) {
    }
}
