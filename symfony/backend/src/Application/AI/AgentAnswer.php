<?php

declare(strict_types=1);

namespace App\Application\AI;

/**
 * What the agent answered, in the application's own terms: no type of the provider's SDK shows here.
 *
 * Today the answer arrives complete and text() hands it over. Streaming is not implemented, but this
 * type is where it would land without breaking the consumers: a way to deliver the answer in parts
 * can be added next to text(), and whoever reads text() keeps working. That is why consumers go
 * through the method and nothing assumes the text is already resolved at construction.
 */
final readonly class AgentAnswer
{
    private function __construct(private string $text)
    {
    }

    public static function ofText(string $text): self
    {
        return new self($text);
    }

    public function text(): string
    {
        return $this->text;
    }
}
