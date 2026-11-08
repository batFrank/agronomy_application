<?php

declare(strict_types=1);

namespace App\Domain\Shared\Media;

final readonly class FileConstraints
{
    /**
     * @throws InvalidMediaConstraintsException
     */
    public function __construct(
        private int $maxFileSize,
    ) {
        $this->validate();
    }

    /**
     * @throws InvalidMediaConstraintsException
     */
    public static function default(): self
    {
        return new self(
            maxFileSize: 100 * 1024 * 1024, // 100 MB
        );
    }

    public function getMaxFileSize(): int
    {
        return $this->maxFileSize;
    }

    /**
     * @throws InvalidMediaConstraintsException
     */
    private function validate(): void
    {
        if ($this->maxFileSize <= 0) {
            throw InvalidMediaConstraintsException::maxSizeMustBePositive($this->maxFileSize);
        }
    }
}
