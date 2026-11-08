<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

use App\Domain\Shared\Exception\DomainException;

class EmailAlreadyInUseException extends DomainException
{
    public static function create(): self
    {
        return new self('Email is already in use.');
    }
}
