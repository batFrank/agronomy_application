<?php

declare(strict_types=1);

namespace App\Domain\Shared\Media;

use App\Domain\Shared\Uuid\Uuid;

final readonly class File extends Media
{
    private const array ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        'text/csv',
        'application/zip',
        'application/x-rar-compressed',
        'application/x-7z-compressed',
    ];

    /**
     * @throws InvalidMimeTypeException
     * @throws InvalidFileSizeException
     */
    /**
     * @throws InvalidMimeTypeException
     * @throws InvalidFileSizeException
     */
    public function __construct(
        Uuid $id,
        string $filename,
        string $path,
        string $mimeType,
        int $size,
        \DateTimeImmutable $uploadedAt,
        private FileConstraints $constraints,
    ) {
        parent::__construct(
            $id,
            $filename,
            $path,
            $mimeType,
            $size,
            self::ALLOWED_MIME_TYPES,
            $uploadedAt,
            $constraints->getMaxFileSize()
        );
    }

    public function getConstraints(): FileConstraints
    {
        return $this->constraints;
    }
}
