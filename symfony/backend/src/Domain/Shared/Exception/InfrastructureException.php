<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exception;

class InfrastructureException extends \Exception
{
    public static function fromPrevious(\Throwable $previous): self
    {
        return new self($previous->getMessage(), $previous->getCode(), $previous);
    }
}
