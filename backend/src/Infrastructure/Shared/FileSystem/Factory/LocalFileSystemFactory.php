<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\FileSystem\Factory;

use App\Domain\Shared\Exception\InfrastructureException;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;

class LocalFileSystemFactory
{
    /**
     * @throws InfrastructureException
     */
    public function __invoke(string $location): Filesystem
    {
        try {
            $adapter = new LocalFilesystemAdapter(
                location: $location,
                visibility: PortableVisibilityConverter::fromArray([
                    'file' => [
                        'public' => 0644,
                        'private' => 0644,
                    ],
                    'dir' => [
                        'public' => 0755,
                        'private' => 0755,
                    ],
                ]),
            );

            return new Filesystem($adapter);
        } catch (UnableToCreateDirectory $e) {
            throw InfrastructureException::fromPrevious($e);
        }
    }
}
