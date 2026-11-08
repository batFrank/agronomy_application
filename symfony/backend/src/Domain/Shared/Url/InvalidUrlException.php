<?php

declare(strict_types=1);

namespace App\Domain\Shared\Url;

use App\Domain\Shared\Exception\DomainException;

class InvalidUrlException extends DomainException
{
    public function __construct(string $url)
    {
        parent::__construct(\sprintf('Invalid url: %s', $url));
    }
}
