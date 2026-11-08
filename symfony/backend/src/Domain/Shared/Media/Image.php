<?php

declare(strict_types=1);

namespace App\Domain\Shared\Media;

use App\Domain\Shared\Uuid\Uuid;

final readonly class Image extends Media
{
    private const array ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
    ];

    /**
     * @throws InvalidMimeTypeException
     * @throws InvalidImageDimensionsException
     * @throws InvalidFileSizeException
     */
    public function __construct(
        Uuid $id,
        string $filename,
        string $path,
        string $mimeType,
        int $size,
        private int $width,
        private int $height,
        \DateTimeImmutable $uploadedAt,
        private ImageConstraints $constraints,
    ) {
        $this->validateDimensions($width, $height, $constraints);
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

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function getConstraints(): ImageConstraints
    {
        return $this->constraints;
    }

    /**
     * @throws InvalidImageDimensionsException
     */
    private function validateDimensions(int $width, int $height, ImageConstraints $constraints): void
    {
        if ($width < $constraints->getMinWidth() || $width > $constraints->getMaxWidth()
            || $height < $constraints->getMinHeight() || $height > $constraints->getMaxHeight()) {
            throw InvalidImageDimensionsException::fromDimensions(
                $width,
                $height,
                $constraints->getMinWidth(),
                $constraints->getMinHeight(),
                $constraints->getMaxWidth(),
                $constraints->getMaxHeight()
            );
        }
    }
}
