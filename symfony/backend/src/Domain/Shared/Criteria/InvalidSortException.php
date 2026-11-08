<?php

declare(strict_types=1);

namespace App\Domain\Shared\Criteria;

class InvalidSortException extends \InvalidArgumentException
{
    public static function invalidField(string $field): self
    {
        return new self(\sprintf('Field %s is not sortable.', $field));
    }
}
