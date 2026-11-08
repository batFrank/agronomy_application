<?php

declare(strict_types=1);

namespace App\Domain\Shared\Email;

final readonly class Email
{
    /**
     * @throws InvalidEmailException
     */
    public function __construct(private string $value)
    {
        $this->validate($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(Email $email): bool
    {
        return $this->value === $email->value();
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * @throws InvalidEmailException
     */
    private function validate(string $value): void
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmailException($value);
        }
    }
}
