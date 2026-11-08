<?php

declare(strict_types=1);

namespace App\Domain\Shared\FileSystem;

use App\Domain\Shared\Exception\InfrastructureException;

interface ChunkUploadInterface
{
    /**
     * Save an uploaded file part.
     *
     * @throws FileSystemException
     */
    public function handle(ChunkFile $file): void;

    /**
     * Merge all uploaded parts into a single File and return the result.
     *
     * @throws ChunkUploadException
     * @throws FileSystemException
     * @throws InfrastructureException
     */
    public function merge(ChunkFile $source): File;
}
