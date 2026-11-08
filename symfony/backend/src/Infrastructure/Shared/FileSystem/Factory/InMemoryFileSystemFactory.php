<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\FileSystem\Factory;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\MimeTypeDetection\FinfoMimeTypeDetector;

class InMemoryFileSystemFactory
{
    public function __invoke(bool $allowTextPlain = false): Filesystem
    {
        $mimeTypeDetector = null;
        if ($allowTextPlain) {
            $mimeTypeDetector = new FinfoMimeTypeDetector(
                inconclusiveMimetypes: [
                    'application/x-empty',
                    'text/x-asm',
                    'application/octet-stream',
                    'inode/x-empty',
                ]
            );
        }

        return new Filesystem(
            new InMemoryFilesystemAdapter(
                mimeTypeDetector: $mimeTypeDetector,
            )
        );
    }
}
