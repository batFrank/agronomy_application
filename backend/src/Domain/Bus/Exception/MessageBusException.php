<?php

declare(strict_types=1);

namespace App\Domain\Bus\Exception;

use App\Domain\Shared\Exception\InfrastructureException;

class MessageBusException extends InfrastructureException
{
    public static function fromPrevious(\Throwable $previous): self
    {
        return new self($previous->getMessage(), $previous->getCode(), $previous);
    }
}
