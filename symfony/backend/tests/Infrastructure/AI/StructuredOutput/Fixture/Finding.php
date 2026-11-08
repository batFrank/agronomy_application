<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\StructuredOutput\Fixture;

final readonly class Finding
{
    /**
     * @param list<Reviewer> $reviewers
     */
    public function __construct(
        public string $text,
        public Priority $priority,
        public array $reviewers,
    ) {
    }
}
