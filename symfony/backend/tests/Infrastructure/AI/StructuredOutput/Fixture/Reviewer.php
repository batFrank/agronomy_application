<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\StructuredOutput\Fixture;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class Reviewer
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        public string $name,
        public ?string $note = null,
    ) {
    }
}
