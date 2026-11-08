<?php

declare(strict_types=1);

namespace App\Domain\Shared\FileSystem;

use App\Domain\Shared\Exception\InfrastructureException;

class FileSystemException extends InfrastructureException
{
    public static function fileSystemNotFound(string $typeName): self
    {
        return new self('FileSystem not found: ' . $typeName);
    }

    public static function destinationCannotBeTmpFileSystem(): self
    {
        return new self('Destination cannot be a temporal path.');
    }

    public static function pathDoesNotExist(string $path): self
    {
        return new self('Path does not exist: ' . $path);
    }

    public static function directoryTransversalIsNotAllowed(): self
    {
        return new self('Directory transversal is not allowed.');
    }
}
