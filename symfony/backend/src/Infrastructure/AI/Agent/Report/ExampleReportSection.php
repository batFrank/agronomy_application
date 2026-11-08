<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Agent\Report;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A section of ExampleReport, as the model is asked to produce it.
 */
final readonly class ExampleReportSection
{
    public function __construct(
        #[Schema(description: 'Short title of the section')]
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 80)]
        public string $title,
        #[Schema(description: 'Content of the section, a short paragraph')]
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 2000)]
        public string $content,
    ) {
    }
}
