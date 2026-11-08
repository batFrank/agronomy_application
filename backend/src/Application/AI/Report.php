<?php

declare(strict_types=1);

namespace App\Application\AI;

/**
 * What the generation agent produced, in the application's own terms: the type a consumer (a command,
 * a handler) gets back from ReportGateway. No type of the provider's SDK shows here, and neither does
 * the DTO the model is asked to fill (which lives in Infrastructure, with the attributes that describe
 * it to the model): the adapter translates one into the other.
 *
 * It is the example of the generation form. A fork replaces it with the shape of its own output.
 */
final readonly class Report
{
    /**
     * @param list<ReportSection> $sections
     */
    private function __construct(
        private string $summary,
        private array $sections,
    ) {
    }

    /**
     * @param list<ReportSection> $sections
     */
    public static function of(string $summary, array $sections): self
    {
        return new self($summary, $sections);
    }

    public function summary(): string
    {
        return $this->summary;
    }

    /**
     * @return list<ReportSection>
     */
    public function sections(): array
    {
        return $this->sections;
    }
}
