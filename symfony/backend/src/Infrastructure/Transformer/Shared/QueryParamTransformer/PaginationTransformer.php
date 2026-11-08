<?php

declare(strict_types=1);

namespace App\Infrastructure\Transformer\Shared\QueryParamTransformer;

use App\Domain\Shared\Criteria\Pagination;

class PaginationTransformer
{
    private const int DEFAULT_PAGE = 1;
    private const int DEFAULT_LIMIT = 10;

    /**
     * @param array<string, mixed> $query
     */
    public static function toPagination(array $query): Pagination
    {
        $page = self::toPositiveInt($query['page'] ?? null, self::DEFAULT_PAGE);
        $limit = self::toPositiveInt($query['limit'] ?? $query['page_size'] ?? null, self::DEFAULT_LIMIT);

        return new Pagination($page, $limit);
    }

    private static function toPositiveInt(mixed $value, int $default): int
    {
        if (!is_numeric($value)) {
            return $default;
        }

        $normalizedValue = (int) $value;

        if ($normalizedValue < 1) {
            return $default;
        }

        return $normalizedValue;
    }
}
