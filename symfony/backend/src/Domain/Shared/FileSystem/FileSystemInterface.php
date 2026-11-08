<?php

declare(strict_types=1);

namespace App\Domain\Shared\FileSystem;

use App\Domain\Shared\Exception\InfrastructureException;

interface FileSystemInterface
{
    /**
     * Move a file from source to destination.
     * Warning: Source file will no longer be accesible.
     *
     * @return File A new file with updated information
     *
     * @throws FileSystemException
     * @throws InfrastructureException
     */
    public function move(
        File $source,
        File $destination,
    ): File;

    /**
     * @return File A new file with updated information
     *
     * @throws FileSystemException
     * @throws InfrastructureException
     */
    public function copy(
        File $source,
        File $destination,
    ): File;

    /**
     * @throws FileSystemException
     * @throws InfrastructureException
     */
    public function delete(File $file): void;

    /**
     * Check if a path exists in a filesystem. The path can be a directory or a file.
     *
     * @throws FileSystemException
     * @throws InfrastructureException
     */
    public function pathExists(string $path, FileSystemType $fileSystemType): bool;

    /**
     * Copy a file content to a predefined stream.
     *
     * @param mixed $outputStream Destination stream
     *
     * @throws FileSystemException
     * @throws InfrastructureException
     *
     * @example output = fopen('php://output', 'wb'); copyToStream($aFile, $output);
     */
    public function copyToStream(File $file, mixed $outputStream): void;
}
