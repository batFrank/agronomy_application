<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Shared\Criteria\Sort;
use App\Domain\Shared\Criteria\SortDirection;

final class DBALSortTransformer
{
    public static function fromSortDirection(SortDirection $direction): string
    {
        return match ($direction) {
            SortDirection::ASC => 'ASC',
            SortDirection::DESC => 'DESC',
        };
    }

    /**
     * @param array<string, string> $sortMap
     */
    public static function fromSortByToDbalSortBy(Sort $sort, ?array $sortMap = null): string
    {
        $sortBy = $sort->getField()->value();

        if ($sortMap && isset($sortMap[$sortBy])) {
            return $sortMap[$sortBy];
        }

        return self::toSnakeCase($sortBy);
    }

    private static function toSnakeCase(string $value): string
    {
        // Handle transitions from lowercase/digit to uppercase (e.g. "createdAt" → "created_At")
        // and from a run of uppercase letters into a new word (e.g. "parseURLAlias" → "parse_URL_Alias")
        $snakeCased = preg_replace(['/([a-z\d])([A-Z])/', '/([A-Z]+)([A-Z][a-z])/'], '$1_$2', $value);

        if (null === $snakeCased) {
            return $value;
        }

        return strtolower($snakeCased);
    }
}
