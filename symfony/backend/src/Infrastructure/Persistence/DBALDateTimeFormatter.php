<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

class DBALDateTimeFormatter
{
    public const string DATE_TIME_FORMAT = 'Y-m-d H:i:s';
    public const string DATE_TIME_FORMAT_WITH_MICROSECONDS = 'Y-m-d H:i:s.u';

    public static function format(?\DateTimeInterface $dateTime): ?string
    {
        if (\is_null($dateTime)) {
            return null;
        }

        return $dateTime->format(self::DATE_TIME_FORMAT_WITH_MICROSECONDS);
    }

    public static function parse(?string $dateTime): ?\DateTimeImmutable
    {
        if (\is_null($dateTime)) {
            return null;
        }

        $parsedDateTime = \DateTimeImmutable::createFromFormat(self::DATE_TIME_FORMAT_WITH_MICROSECONDS, $dateTime);

        if (!$parsedDateTime) {
            $parsedDateTime = \DateTimeImmutable::createFromFormat(self::DATE_TIME_FORMAT, $dateTime);
        }

        return $parsedDateTime ?: null;
    }
}
