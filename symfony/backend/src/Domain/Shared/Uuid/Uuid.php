<?php

declare(strict_types=1);

namespace App\Domain\Shared\Uuid;

readonly class Uuid
{
    /**
     * @throws InvalidUuidException
     */
    public function __construct(private string $value)
    {
        $this->validate();
    }

    public function value(): string
    {
        return $this->value;
    }

    /**
     * Case-insensitive: the regex accepts uppercase, and a canonical value read back from a uuid
     * column (e.g. PostgreSQL) is always lowercase, so two valid representations of the same
     * identity must compare equal even if one never round-tripped through storage.
     */
    public function equals(self $uuid): bool
    {
        return mb_strtolower($this->value()) === mb_strtolower($uuid->value());
    }

    public function __toString(): string
    {
        return $this->value();
    }

    /**
     * @throws InvalidUuidException
     */
    private function validate(): void
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $this->value())) {
            throw new InvalidUuidException($this->value());
        }
    }
}
