<?php

declare(strict_types=1);

namespace App\Domain\User;

enum UserRole
{
    case User;
    case Manager;
    case Admin;
}
