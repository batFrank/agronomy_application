<?php

declare(strict_types=1);

namespace App\Domain\Shared\Uuid;

use App\Domain\Shared\Exception\DomainException;

class InvalidUuidException extends DomainException
{
    public function __construct(string $uuid)
    {
        parent::__construct(\sprintf('Invalid UUID: %s', $uuid));
    }
}
