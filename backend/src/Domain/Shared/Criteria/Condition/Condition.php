<?php

declare(strict_types=1);

namespace App\Domain\Shared\Criteria\Condition;

enum Condition
{
    case Equal;
    case NotEqual;
    case IsNull;
    case IsNotNull;

    // Extensible based on requirements
}
