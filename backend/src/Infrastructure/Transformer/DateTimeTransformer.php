<?php

declare(strict_types=1);

namespace App\Infrastructure\Transformer;

class DateTimeTransformer
{
    public const string DATE_TIME_FORMAT = 'Y-m-d H:i:s';

    public static function toOutput(\DateTimeInterface $dateTime): string
    {
        return $dateTime->format(self::DATE_TIME_FORMAT);
    }

    /**
     * @throws \DateMalformedStringException
     */
    public static function fromInput(string $dateTime): \DateTimeImmutable
    {
        // Try to create from the standard format first
        $result = \DateTimeImmutable::createFromFormat(self::DATE_TIME_FORMAT, $dateTime);

        // If that fails, try to parse as a general date/time string
        if (false === $result) {
            $result = new \DateTimeImmutable($dateTime);
        }

        return $result;
    }
}
