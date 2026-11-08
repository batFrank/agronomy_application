<?php

declare(strict_types=1);

namespace App\Domain\Shared\Hydrator;

abstract class HydrationCriteria
{
    final public function __construct()
    {
    }

    /**
     * @return static
     */
    public static function createEmpty(): self
    {
        return new static();
    }
}
