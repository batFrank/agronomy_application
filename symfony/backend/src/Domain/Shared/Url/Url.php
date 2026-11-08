<?php

declare(strict_types=1);

namespace App\Domain\Shared\Url;

class Url
{
    /**
     * @throws InvalidUrlException
     */
    public function __construct(private string $value)
    {
        $this->validate($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(Url $url): bool
    {
        return $this->value === $url->value();
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * @throws InvalidUrlException
     */
    private function validate(string $value): void
    {
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            throw new InvalidUrlException($value);
        }
    }
}
