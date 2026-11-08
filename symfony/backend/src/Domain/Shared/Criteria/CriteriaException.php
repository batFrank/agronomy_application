<?php

declare(strict_types=1);

namespace App\Domain\Shared\Criteria;

class CriteriaException extends \Exception
{
    public static function filterByEmptyIdsIsNotAllowed(): self
    {
        return new self('Filter by empty ids is not allowed');
    }

    public static function invalidParamType(string $expected, string $actual): self
    {
        return new self(\sprintf('Invalid param type. Expected %s, actual: %s', $expected, $actual));
    }
}
