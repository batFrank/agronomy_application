<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Agent\Report;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * What the model is asked to produce (`response_format`): the schema it sees is built from this class,
 * its `#[Schema]` descriptions and its constraints. StructuredOutputDecoder turns the answer into it,
 * rejecting what the schema forbids, and validates it against those constraints; an answer that does
 * not fit never gets out of the adapter, which hands the application a Report, not this.
 *
 * It is the example of the generation form. A fork replaces it with its own output.
 */
final readonly class ExampleReport
{
    /**
     * @param list<ExampleReportSection> $sections
     */
    public function __construct(
        #[Schema(description: 'Executive summary, three sentences at most')]
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 600)]
        public string $summary,
        #[Assert\Count(min: 1, max: 5)]
        #[Assert\Valid]
        public array $sections,
    ) {
    }
}
