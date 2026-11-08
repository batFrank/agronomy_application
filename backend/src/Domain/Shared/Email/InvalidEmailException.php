<?php

declare(strict_types=1);

namespace App\Domain\Shared\Email;

use App\Domain\Shared\Exception\DomainException;

class InvalidEmailException extends DomainException
{
    public function __construct(string $email)
    {
        parent::__construct(\sprintf('Invalid email address: %s', $email));
    }
}
