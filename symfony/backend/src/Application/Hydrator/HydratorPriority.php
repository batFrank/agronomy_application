<?php

declare(strict_types=1);

namespace App\Application\Hydrator;

enum HydratorPriority
{
    case First;
    case Second;
    case Third;

    /**
     * @return HydratorPriority[]
     */
    public static function getInOrder(): array
    {
        return [self::First, self::Second, self::Third];
    }
}
