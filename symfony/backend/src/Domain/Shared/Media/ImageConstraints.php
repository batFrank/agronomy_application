<?php

declare(strict_types=1);

namespace App\Domain\Shared\Media;

final readonly class ImageConstraints
{
    /**
     * @throws InvalidMediaConstraintsException
     */
    public function __construct(
        private int $maxFileSize,
        private int $minWidth,
        private int $maxWidth,
        private int $minHeight,
        private int $maxHeight,
    ) {
        $this->validate();
    }

    /**
     * @throws InvalidMediaConstraintsException
     */
    public static function default(): self
    {
        return new self(
            maxFileSize: 10 * 1024 * 1024, // 10 MB
            minWidth: 1,
            maxWidth: 10000,
            minHeight: 1,
            maxHeight: 10000,
        );
    }

    public function getMaxFileSize(): int
    {
        return $this->maxFileSize;
    }

    public function getMinWidth(): int
    {
        return $this->minWidth;
    }

    public function getMaxWidth(): int
    {
        return $this->maxWidth;
    }

    public function getMinHeight(): int
    {
        return $this->minHeight;
    }

    public function getMaxHeight(): int
    {
        return $this->maxHeight;
    }

    /**
     * @throws InvalidMediaConstraintsException
     */
    private function validate(): void
    {
        if ($this->maxFileSize <= 0) {
            throw InvalidMediaConstraintsException::maxSizeMustBePositive($this->maxFileSize);
        }

        if ($this->minWidth <= 0) {
            throw InvalidMediaConstraintsException::minDimensionMustBePositive($this->minWidth);
        }

        if ($this->minHeight <= 0) {
            throw InvalidMediaConstraintsException::minDimensionMustBePositive($this->minHeight);
        }

        if ($this->maxWidth <= $this->minWidth) {
            throw InvalidMediaConstraintsException::maxDimensionMustBeGreaterThanMin(
                $this->minWidth,
                $this->maxWidth
            );
        }

        if ($this->maxHeight <= $this->minHeight) {
            throw InvalidMediaConstraintsException::maxDimensionMustBeGreaterThanMin(
                $this->minHeight,
                $this->maxHeight
            );
        }
    }
}
