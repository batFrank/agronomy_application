<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Shared\Locale\InvalidLocaleException;
use App\Domain\Shared\Locale\Locale;

class DBALLocaleTransformer
{
    public static function fromLocaleToString(Locale $locale): string
    {
        return strtolower($locale->name);
    }

    /**
     * @throws InvalidLocaleException
     */
    public static function fromStringToLocale(string $localeAsString): Locale
    {
        try {
            return Locale::{strtoupper($localeAsString)};
        } catch (\Error) {
            throw InvalidLocaleException::fromString($localeAsString);
        }
    }
}
