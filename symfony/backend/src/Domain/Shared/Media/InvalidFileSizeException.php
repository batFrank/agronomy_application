<?php

declare(strict_types=1);

namespace App\Domain\Shared\Media;

use App\Domain\Shared\Exception\DomainException;

class InvalidFileSizeException extends DomainException
{
    public function __construct(int $size, int $maxSize)
    {
        parent::__construct(
            \sprintf('Invalid file size: %d bytes. Maximum allowed: %d bytes', $size, $maxSize)
        );
    }

    public static function fromSize(int $size, int $maxSize): self
    {
        return new self($size, $maxSize);
    }
}
