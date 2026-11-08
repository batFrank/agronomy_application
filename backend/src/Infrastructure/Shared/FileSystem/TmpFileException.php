<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\FileSystem;

use App\Domain\Shared\FileSystem\FileSystemException;

class TmpFileException extends FileSystemException
{
    public static function unableToReadFile(): self
    {
        return new self('Unable to read file.');
    }

    public static function contentIsNotValid(): self
    {
        return new self('Content is not valid.');
    }

    public static function fileIsNotAValidImage(): self
    {
        return new self('File is not a valid image.');
    }

    public static function unableToWriteToTmpDirectory(): self
    {
        return new self('Unable to write to tmp directory.');
    }

    public static function unableToGetMimeType(): self
    {
        return new self('Unable to get mime type.');
    }
}
