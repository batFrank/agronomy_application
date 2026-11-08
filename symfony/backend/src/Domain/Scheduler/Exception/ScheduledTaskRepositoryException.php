<?php

declare(strict_types=1);

namespace App\Domain\Scheduler\Exception;

use App\Domain\Shared\Exception\InfrastructureException;

class ScheduledTaskRepositoryException extends InfrastructureException
{
    public static function fromPrevious(\Throwable $previous): self
    {
        return new self($previous->getMessage(), $previous->getCode(), $previous);
    }

    public static function invalidMessageClass(string $messageClass): self
    {
        return new self(\sprintf('Message class "%s" does not exist', $messageClass));
    }
}
