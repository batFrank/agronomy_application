<?php

declare(strict_types=1);

namespace App\Tests\Mother\Domain\Shared\Media;

use App\Domain\Shared\Media\File;
use App\Domain\Shared\Media\FileConstraints;
use App\Domain\Shared\Media\InvalidFileSizeException;
use App\Domain\Shared\Media\InvalidMediaConstraintsException;
use App\Domain\Shared\Media\InvalidMimeTypeException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Shared\Uuid\Uuid;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;

class FileMother
{
    /**
     * @throws InvalidUuidException
     * @throws InvalidFileSizeException
     * @throws InvalidMimeTypeException
     * @throws InvalidMediaConstraintsException
     */
    public static function create(
        ?Uuid $id = null,
        ?string $filename = null,
        ?string $path = null,
        ?string $mimeType = null,
        ?int $size = null,
        ?\DateTimeImmutable $uploadedAt = null,
        ?FileConstraints $constraints = null,
    ): File {
        return new File(
            id: $id ?? UuidMother::create(),
            filename: $filename ?? 'test-document.pdf',
            path: $path ?? '/uploads/documents',
            mimeType: $mimeType ?? 'application/pdf',
            size: $size ?? 1024 * 500, // 500 KB
            uploadedAt: $uploadedAt ?? new \DateTimeImmutable(),
            constraints: $constraints ?? FileConstraints::default(),
        );
    }
}
