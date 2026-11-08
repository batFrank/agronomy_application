<?php

declare(strict_types=1);

namespace App\Tests\Mother\Domain\Shared\Media;

use App\Domain\Shared\Media\Image;
use App\Domain\Shared\Media\ImageConstraints;
use App\Domain\Shared\Media\InvalidFileSizeException;
use App\Domain\Shared\Media\InvalidImageDimensionsException;
use App\Domain\Shared\Media\InvalidMediaConstraintsException;
use App\Domain\Shared\Media\InvalidMimeTypeException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Shared\Uuid\Uuid;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;

class ImageMother
{
    /**
     * @throws InvalidMediaConstraintsException
     * @throws InvalidFileSizeException
     * @throws InvalidImageDimensionsException
     * @throws InvalidUuidException
     * @throws InvalidMimeTypeException
     */
    public static function create(
        ?Uuid $id = null,
        ?string $filename = null,
        ?string $path = null,
        ?string $mimeType = null,
        ?int $size = null,
        ?int $width = null,
        ?int $height = null,
        ?\DateTimeImmutable $uploadedAt = null,
        ?ImageConstraints $constraints = null,
    ): Image {
        return new Image(
            id: $id ?? UuidMother::create(),
            filename: $filename ?? 'test-image.jpg',
            path: $path ?? '/uploads/images',
            mimeType: $mimeType ?? 'image/jpeg',
            size: $size ?? 1024 * 100, // 100 KB
            width: $width ?? 800,
            height: $height ?? 600,
            uploadedAt: $uploadedAt ?? new \DateTimeImmutable(),
            constraints: $constraints ?? ImageConstraints::default(),
        );
    }
}
