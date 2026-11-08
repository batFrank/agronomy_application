<?php

declare(strict_types=1);

namespace App\Application\AI;

/**
 * One section of a Report, in the application's own terms.
 */
final readonly class ReportSection
{
    private function __construct(
        private string $title,
        private string $content,
    ) {
    }

    public static function of(string $title, string $content): self
    {
        return new self($title, $content);
    }

    public function title(): string
    {
        return $this->title;
    }

    public function content(): string
    {
        return $this->content;
    }
}
