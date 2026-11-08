<?php

declare(strict_types=1);

namespace App\Domain\Shared\FileSystem;

final class ChunkUploadException extends FileSystemException
{
    public static function noUploadedParts(): self
    {
        return new self('No parts has been uploaded.');
    }

    public static function failedToOpenFileDescriptor(): self
    {
        return new self('Failed to open file descriptor.');
    }

    public static function uploadedPartsInconsistency(): self
    {
        return new self('Uploaded files parts are inconsistent.');
    }

    public static function failedToMergeParts(): self
    {
        return new self('Failed to merge parts.');
    }
}
