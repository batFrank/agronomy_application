<?php

declare(strict_types=1);

namespace App\Domain\Shared\Media;

use App\Domain\Shared\Exception\DomainException;

class InvalidMimeTypeException extends DomainException
{
    public function __construct(string $mimeType, string $expectedTypes)
    {
        parent::__construct(
            \sprintf('Invalid MIME type: %s. Expected one of: %s', $mimeType, $expectedTypes)
        );
    }

    /**
     * @param string[] $allowedMimeTypes
     */
    public static function fromMimeType(string $mimeType, array $allowedMimeTypes): self
    {
        return new self($mimeType, implode(', ', $allowedMimeTypes));
    }
}
