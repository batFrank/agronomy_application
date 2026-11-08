<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

use App\Domain\Shared\Exception\ForbiddenException;

class UserForbiddenException extends ForbiddenException
{
    public static function managerOnlyAllowedToSeeOwnUsers(): self
    {
        return new self('Only managers are allowed to see other users');
    }

    public static function cannotUpdateUser(): self
    {
        return new self('You are not authorized to update this user');
    }

    public static function cannotViewUser(): self
    {
        return new self('You are not authorized to view this user');
    }
}
