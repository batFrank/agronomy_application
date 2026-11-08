<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\FileSystem;

use App\Domain\Shared\FileSystem\File;
use App\Domain\Shared\FileSystem\FileSystemException;
use App\Domain\Shared\FileSystem\FileSystemType;
use Symfony\Component\HttpFoundation\File\File as SymfonyFile;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * This is a temporal file. In relation with the base File, this does not need
 * a file system for data reading. All information is valid for the duration of
 * the script. The file is expected to be removed at the end of the script.
 */
final readonly class TmpFile extends File
{
    public function __construct(
        string $path,
        string $filename,
        ?string $displayName,
        string $mimeType,
        int $fileSize,
        private SymfonyFile $file,
        FileSystemType $fileSystemType = FileSystemType::TmpLocal,
    ) {
        parent::__construct($path, $filename, $displayName, $mimeType, $fileSize, $fileSystemType);
    }

    public function getFile(): SymfonyFile
    {
        return $this->file;
    }

    /**
     * @return string return absolute path to file
     */
    public function getPath(): string
    {
        return $this->file->getRealPath();
    }

    /**
     * @return resource open the file and return the resource in read-byte mode
     *
     * @throws FileSystemException
     */
    public function getResource(): mixed
    {
        $resource = @fopen($this->getPath(), 'rb');
        if (false === $resource) {
            throw TmpFileException::unableToReadFile();
        }

        return $resource;
    }

    /**
     * @throws TmpFileException
     */
    public static function createFromFile(SymfonyFile $file): self
    {
        return new self(
            path: $file->getPath(),
            filename: $file->getFilename(),
            displayName: ($file instanceof UploadedFile) ? $file->getClientOriginalName() : null,
            mimeType: $file->getMimeType() ?? throw TmpFileException::unableToGetMimeType(),
            fileSize: $file->getSize(),
            file: $file,
            fileSystemType: FileSystemType::TmpLocal,
        );
    }

    /**
     * @throws TmpFileException
     */
    public static function createImageFileFromContent(string $content): self
    {
        $path = tempnam(sys_get_temp_dir(), 'img');

        if (!preg_match('/^data:image\/(?<extension>(?:png|gif|jpg|jpeg));base64,(?<image>.+)$/', $content, $matches)) {
            throw TmpFileException::contentIsNotValid();
        }

        $data = base64_decode($matches['image']);
        $extension = $matches['extension'];
        $path .= '.' . $extension;

        if (false === file_put_contents($path, $data)) {
            throw TmpFileException::unableToWriteToTmpDirectory();
        }

        $file = new SymfonyFile(
            path: $path,
        );

        if (null === $file->getMimeType() || false === str_starts_with($file->getMimeType(), 'image')) {
            throw TmpFileException::fileIsNotAValidImage();
        }

        return self::createFromFile($file);
    }

    /**
     * Create a file from trivial content.
     *
     * @throws TmpFileException
     */
    public static function createFileFromContent(string $content): self
    {
        $path = tempnam(sys_get_temp_dir(), 'file');

        if (false === file_put_contents($path, $content)) {
            throw TmpFileException::unableToWriteToTmpDirectory();
        }

        $file = new SymfonyFile(
            path: $path,
        );

        return self::createFromFile($file);
    }
}
