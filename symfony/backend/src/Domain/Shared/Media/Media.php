<?php

declare(strict_types=1);

namespace App\Domain\Shared\Media;

use App\Domain\Shared\Uuid\Uuid;

abstract readonly class Media
{
    /**
     * @param string[] $allowedMimeTypes
     *
     * @throws InvalidMimeTypeException
     * @throws InvalidFileSizeException
     */
    protected function __construct(
        private Uuid $id,
        private string $filename,
        private string $path,
        private string $mimeType,
        private int $size,
        private array $allowedMimeTypes,
        private \DateTimeImmutable $uploadedAt,
        private int $maxFileSize,
    ) {
        $this->validateMimeType($mimeType, $allowedMimeTypes);
        $this->validateFileSize($size);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getFullPath(): string
    {
        return rtrim($this->path, \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR . $this->filename;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getUploadedAt(): \DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    /**
     * @return string[]
     */
    public function getAllowedMimeTypes(): array
    {
        return $this->allowedMimeTypes;
    }

    /**
     * @param string[] $allowedMimeTypes
     *
     * @throws InvalidMimeTypeException
     */
    protected function validateMimeType(string $mimeType, array $allowedMimeTypes): void
    {
        if (!\in_array($mimeType, $allowedMimeTypes, true)) {
            throw InvalidMimeTypeException::fromMimeType($mimeType, $allowedMimeTypes);
        }
    }

    /**
     * @throws InvalidFileSizeException
     */
    protected function validateFileSize(int $size): void
    {
        if ($size > $this->maxFileSize) {
            throw InvalidFileSizeException::fromSize($size, $this->maxFileSize);
        }
    }
}
