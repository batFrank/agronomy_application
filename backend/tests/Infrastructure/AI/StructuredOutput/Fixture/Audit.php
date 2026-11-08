<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\StructuredOutput\Fixture;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * What a fork could ask for: a date, an enum, an object, a nullable object and a list of objects that
 * hold a list of objects, so the decoder is exercised at more than the two levels of the example.
 */
final readonly class Audit
{
    /**
     * @param list<Finding> $findings
     */
    public function __construct(
        public \DateTimeImmutable $performedAt,
        #[Assert\Valid]
        public Reviewer $lead,
        #[Assert\Valid]
        public ?Reviewer $backup,
        #[Assert\Count(min: 1)]
        #[Assert\Valid]
        public array $findings,
    ) {
    }
}
