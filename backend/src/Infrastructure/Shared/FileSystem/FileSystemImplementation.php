<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\FileSystem;

use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\FileSystem\File;
use App\Domain\Shared\FileSystem\FileSystemException;
use App\Domain\Shared\FileSystem\FileSystemInterface;
use App\Domain\Shared\FileSystem\FileSystemType;
use League\Flysystem\Filesystem;

final readonly class FileSystemImplementation implements FileSystemInterface
{
    /**
     * @param array<string, Filesystem> $fileSystems
     */
    public function __construct(
        private array $fileSystems,
    ) {
    }

    #[\Override]
    public function move(File $source, File $destination): File
    {
        $this->validatePath($destination->getPath());
        $this->validatePath($source->getPath());

        if (FileSystemType::TmpLocal === $destination->getFileSystemType()) {
            throw FileSystemException::destinationCannotBeTmpFileSystem();
        }

        $destinationFileSystem = $this->getFileSystem($destination->getFileSystemType());
        if (!$source instanceof TmpFile && $source->getFileSystemType() === $destination->getFileSystemType()) {
            // Allow the filesystem to handle its own files
            try {
                $destinationFileSystem->move(
                    source: $source->getFullPath(),
                    destination: $destination->getFullPath(),
                );

                return new File(
                    path: $destination->getPath(),
                    filename: $destination->getFilename(),
                    displayName: $destination->getDisplayName(),
                    mimeType: $destinationFileSystem->mimeType($destination->getFullPath()),
                    fileSize: $destinationFileSystem->fileSize($destination->getFullPath()),
                    fileSystemType: $destination->getFileSystemType(),
                );
            } catch (\Throwable $e) {
                throw FileSystemException::fromPrevious($e);
            }
        }

        // Different filesystems or source is TmpFile
        try {
            $sourceFilesystem = null;
            if ($source instanceof TmpFile) {
                // Is an uploaded file or generated file
                $sourceStream = $source->getResource();
            } else {
                $sourceFilesystem = $this->getFileSystem($source->getFileSystemType());
                $sourceStream = $sourceFilesystem->readStream(
                    location: $source->getFullPath()
                );
            }

            $destinationFileSystem->writeStream(
                location: $destination->getFullPath(),
                contents: $sourceStream
            );

            // Remove the source file to be considered a move action
            $sourceFilesystem?->delete($source->getFullPath());

            return new File(
                path: $destination->getPath(),
                filename: $destination->getFilename(),
                displayName: $destination->getDisplayName(),
                mimeType: $destinationFileSystem->mimeType($destination->getFullPath()),
                fileSize: $destinationFileSystem->fileSize($destination->getFullPath()),
                fileSystemType: $destination->getFileSystemType(),
            );
        } catch (\Throwable $e) {
            throw FileSystemException::fromPrevious($e);
        }
    }

    /**
     * @param File $destination Cannot be a tmp file
     *
     * @throws FileSystemException
     * @throws InfrastructureException
     */
    #[\Override]
    public function copy(File $source, File $destination): File
    {
        $this->validatePath($source->getPath());
        $this->validatePath($destination->getPath());

        if (FileSystemType::TmpLocal === $destination->getFileSystemType()) {
            throw FileSystemException::destinationCannotBeTmpFileSystem();
        }

        if ($source instanceof TmpFile) {
            return $this->move($source, $destination);
        }
        $this->validatePath($destination->getPath());

        $destinationFileSystem = $this->getFileSystem($destination->getFileSystemType());
        if ($source->getFileSystemType() === $destination->getFileSystemType()) {
            // Is same filesystem, let the filesystem handle its own files
            try {
                $destinationFileSystem->copy($source->getFullPath(), $destination->getFullPath());

                return new File(
                    path: $destination->getPath(),
                    filename: $destination->getFilename(),
                    displayName: $destination->getDisplayName(),
                    mimeType: $destinationFileSystem->mimeType($destination->getFullPath()),
                    fileSize: $destinationFileSystem->fileSize($destination->getFullPath()),
                    fileSystemType: $destination->getFileSystemType(),
                );
            } catch (\Throwable $e) {
                throw FileSystemException::fromPrevious($e);
            }
        }

        // Copy file between different file systems
        $sourceFilesystem = $this->getFileSystem($source->getFileSystemType());

        try {
            $sourceStream = $sourceFilesystem->readStream(
                location: $source->getFullPath()
            );

            $destinationFileSystem->writeStream(
                location: $destination->getFullPath(),
                contents: $sourceStream
            );

            return new File(
                path: $destination->getPath(),
                filename: $destination->getFilename(),
                displayName: $destination->getDisplayName(),
                mimeType: $destinationFileSystem->mimeType($destination->getFullPath()),
                fileSize: $destinationFileSystem->fileSize($destination->getFullPath()),
                fileSystemType: $destination->getFileSystemType(),
            );
        } catch (\League\Flysystem\FilesystemException $e) {
            throw FileSystemException::fromPrevious($e);
        }
    }

    #[\Override]
    public function delete(File $file): void
    {
        $this->validatePath($file->getPath());
        $fileSystem = $this->getFileSystem($file->getFileSystemType());

        if (!$this->pathExists(
            path: $file->getFullPath(),
            fileSystemType: $file->getFileSystemType(),
        )) {
            throw FileSystemException::pathDoesNotExist(path: $file->getFullPath());
        }

        try {
            $fileSystem->delete($file->getFullPath());
        } catch (\Throwable $e) {
            throw FileSystemException::fromPrevious($e);
        }
    }

    #[\Override]
    public function pathExists(string $path, FileSystemType $fileSystemType): bool
    {
        $this->validatePath($path);
        $fileSystem = $this->getFileSystem($fileSystemType);

        try {
            if ($fileSystem->fileExists($path)) {
                return true;
            }

            if ($fileSystem->directoryExists($path)) {
                return true;
            }

            return false;
        } catch (\Throwable $e) {
            throw FileSystemException::fromPrevious($e);
        }
    }

    #[\Override]
    public function copyToStream(File $file, mixed $outputStream): void
    {
        $this->validatePath($file->getPath());
        if (!\is_resource($outputStream)) {
            throw new FileSystemException('$outputStream must be a resource.');
        }

        $sourceFileSystem = $this->getFileSystem($file->getFileSystemType());
        try {
            $sourceStream = $sourceFileSystem->readStream(
                location: $file->getFullPath(),
            );
        } catch (\League\Flysystem\FilesystemException $e) {
            throw FileSystemException::fromPrevious($e);
        }

        if (false === stream_copy_to_stream($sourceStream, $outputStream)) {
            throw new FileSystemException('Failed to copy to stream.');
        }
    }

    /**
     * @throws FileSystemException
     */
    private function getFileSystem(FileSystemType $type): Filesystem
    {
        $fileSystem = $this->fileSystems[$type->toString()] ?? null;
        if (null === $fileSystem) {
            throw FileSystemException::fileSystemNotFound($type->name);
        }

        return $fileSystem;
    }

    /**
     * @throws FileSystemException
     */
    private function validatePath(string $path): void
    {
        if (str_contains($path, '..')) {
            throw FileSystemException::directoryTransversalIsNotAllowed();
        }
    }
}
