<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\FileSystem;

use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\FileSystem\ChunkFile;
use App\Domain\Shared\FileSystem\ChunkUploadException;
use App\Domain\Shared\FileSystem\ChunkUploadInterface;
use App\Domain\Shared\FileSystem\File;
use App\Domain\Shared\FileSystem\FileSystemException;
use App\Domain\Shared\FileSystem\FileSystemInterface;
use App\Domain\Shared\FileSystem\FileSystemType;
use League\Flysystem\Filesystem;
use League\Flysystem\StorageAttributes;

final readonly class ChunkUploadImplementation implements ChunkUploadInterface
{
    public function __construct(
        private FileSystemInterface $fileSystemInterface,
        private Filesystem $chunkFileSystem,
    ) {
    }

    #[\Override]
    public function handle(ChunkFile $file): void
    {
        $directoryName = md5($file->getIdentifier()); // Generate a hash for the directory

        try {
            if (1 === $file->getCurrentPart()) {
                // Is the first part, clean directory if exists
                if ($this->chunkFileSystem->directoryExists($directoryName)) {
                    $this->chunkFileSystem->deleteDirectory($directoryName);
                }
            } else {
                // Verify other parts are available
                $uploadedParts = $this->getUploadedParts(
                    directoryName: $directoryName,
                );
                if (\count($uploadedParts) !== ($file->getCurrentPart() - 1)) {
                    // If current part is 4, the dir must have 3 parts uploaded
                    // Clean directory to retry upload
                    if ($this->chunkFileSystem->directoryExists($directoryName)) {
                        $this->chunkFileSystem->deleteDirectory($directoryName);
                    }
                    throw ChunkUploadException::uploadedPartsInconsistency();
                }
            }

            $destination = new File(
                path: $directoryName,
                filename: \sprintf('part_%03d', $file->getCurrentPart()),
                displayName: null,
                mimeType: 'application/octet-stream',
                fileSize: 0,
                fileSystemType: FileSystemType::ChunksLocal,
            );

            $this->fileSystemInterface->move($file->getFile(), $destination);
        } catch (\League\Flysystem\FilesystemException $e) {
            throw ChunkUploadException::fromPrevious($e);
        }
    }

    #[\Override]
    public function merge(ChunkFile $source): File
    {
        $directoryName = md5($source->getIdentifier()); // Generate a hash for the directory
        $uploadedParts = $this->getUploadedParts(
            directoryName: $directoryName,
        );
        $totalParts = \count($uploadedParts);
        if (0 === $totalParts) {
            throw ChunkUploadException::noUploadedParts();
        }

        $path = tempnam(sys_get_temp_dir(), 'upl_');

        $output = fopen($path, 'w+b');
        if (false === $output) {
            throw ChunkUploadException::failedToOpenFileDescriptor();
        }

        try {
            for ($i = 0; $i < $totalParts; ++$i) {
                $sourceStream = $this->chunkFileSystem->readStream($uploadedParts[$i]['path']);
                if (false === stream_copy_to_stream($sourceStream, $output)) {
                    throw ChunkUploadException::failedToMergeParts();
                }
                fclose($sourceStream);
            }

            // Remove uploaded files
            $this->chunkFileSystem->deleteDirectory($directoryName);

            return TmpFile::createFromFile(
                new \Symfony\Component\HttpFoundation\File\File(
                    path: $path
                )
            );
        } catch (\Throwable $e) {
            throw ChunkUploadException::fromPrevious($e);
        } finally {
            if (\is_resource($output)) {
                fclose($output);
            }
        }
    }

    /**
     * @return StorageAttributes[] Ordered list by path
     *
     * @throws FileSystemException
     * @throws InfrastructureException
     */
    private function getUploadedParts(string $directoryName): array
    {
        try {
            return $this->chunkFileSystem->listContents($directoryName)
                ->filter(fn (StorageAttributes $attributes) => $attributes->isFile())
                ->sortByPath()
                ->toArray();
        } catch (\Throwable $e) {
            throw FileSystemException::fromPrevious($e);
        }
    }
}
