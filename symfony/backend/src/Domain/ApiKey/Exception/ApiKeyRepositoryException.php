<?php

declare(strict_types=1);

namespace App\Domain\ApiKey\Exception;

use App\Domain\Shared\Exception\InfrastructureException;

class ApiKeyRepositoryException extends InfrastructureException
{
    public static function fromPrevious(\Throwable $previous): self
    {
        return new self($previous->getMessage(), $previous->getCode(), $previous);
    }

    public static function duplicateApiKey(): self
    {
        return new self('Api key already exists');
    }
}
