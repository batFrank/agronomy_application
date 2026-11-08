<?php

declare(strict_types=1);

namespace App\Domain\Shared\Date;

final readonly class Date
{
    private \DateTimeImmutable $value;

    private function __construct(\DateTimeImmutable $value)
    {
        // Force midnight normalization
        $this->value = $value->setTime(0, 0, 0);
    }

    /**
     * @throws \DateMalformedStringException
     */
    public static function fromString(string $dateString): self
    {
        return new self(new \DateTimeImmutable($dateString));
    }

    public static function fromDateTime(\DateTimeImmutable $dateTime): self
    {
        return new self($dateTime);
    }

    public function value(): \DateTimeImmutable
    {
        return $this->value;
    }

    public function format(string $format = 'Y-m-d'): string
    {
        return $this->value->format($format);
    }

    public function equals(Date $other): bool
    {
        return $this->format() === $other->format();
    }
}
