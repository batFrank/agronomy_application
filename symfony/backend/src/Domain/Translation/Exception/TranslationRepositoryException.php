<?php

declare(strict_types=1);

namespace App\Domain\Translation\Exception;

use App\Domain\Shared\Exception\InfrastructureException;

class TranslationRepositoryException extends InfrastructureException
{
    public static function fromPrevious(\Throwable $previous): self
    {
        return new self($previous->getMessage(), $previous->getCode(), $previous);
    }
}
