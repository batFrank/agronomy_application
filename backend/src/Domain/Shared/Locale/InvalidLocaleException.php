<?php

declare(strict_types=1);

namespace App\Domain\Shared\Locale;

use App\Domain\Shared\Exception\DomainException;

class InvalidLocaleException extends DomainException
{
    public static function fromString(string $locale): self
    {
        return new self(\sprintf('Invalid locale: %s', $locale));
    }
}
