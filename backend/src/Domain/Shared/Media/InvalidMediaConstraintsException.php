<?php

declare(strict_types=1);

namespace App\Domain\Shared\Media;

use App\Domain\Shared\Exception\DomainException;

class InvalidMediaConstraintsException extends DomainException
{
    public static function maxSizeMustBePositive(int $maxSize): self
    {
        return new self(\sprintf('Max file size must be positive, got: %d', $maxSize));
    }

    public static function minDimensionMustBePositive(int $dimension): self
    {
        return new self(\sprintf('Min dimension must be positive, got: %d', $dimension));
    }

    public static function maxDimensionMustBeGreaterThanMin(int $min, int $max): self
    {
        return new self(\sprintf('Max dimension (%d) must be greater than min (%d)', $max, $min));
    }
}
