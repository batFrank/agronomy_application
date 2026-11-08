<?php

declare(strict_types=1);

namespace App\Domain\Shared\FileSystem;

/**
 * Main File container. It contains basic information for a file.
 *
 * @see FileSystemInterface
 */
readonly class File
{
    public function __construct(
        private string $path,
        private string $filename,
        private ?string $displayName,
        private string $mimeType,
        private int $fileSize,
        private FileSystemType $fileSystemType = FileSystemType::PrivateLocal,
    ) {
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getFullPath(string $separator = '/'): string
    {
        $path = rtrim($this->path, '\\/');

        return $path . $separator . $this->filename;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    public function getFileSystemType(): FileSystemType
    {
        return $this->fileSystemType;
    }
}
