<?php

declare(strict_types=1);

namespace App\Domain\Shared\Media;

use App\Domain\Shared\Exception\DomainException;

class InvalidImageDimensionsException extends DomainException
{
    public function __construct(int $width, int $height, int $minWidth, int $minHeight, int $maxWidth, int $maxHeight)
    {
        parent::__construct(
            \sprintf(
                'Invalid image dimensions: %dx%d. Expected: width between %d-%d, height between %d-%d',
                $width,
                $height,
                $minWidth,
                $maxWidth,
                $minHeight,
                $maxHeight
            )
        );
    }

    public static function fromDimensions(
        int $width,
        int $height,
        int $minWidth,
        int $minHeight,
        int $maxWidth,
        int $maxHeight
    ): self {
        return new self($width, $height, $minWidth, $minHeight, $maxWidth, $maxHeight);
    }
}
