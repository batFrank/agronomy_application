<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

use App\Domain\Shared\Exception\DomainException;

class InvalidPasswordException extends DomainException
{
    public const int MIN_LENGTH = 6;

    public static function tooShort(int $minLength): self
    {
        return new self(\sprintf('Password must be at least %d characters.', $minLength));
    }
}
