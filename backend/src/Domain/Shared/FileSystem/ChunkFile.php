<?php

declare(strict_types=1);

namespace App\Domain\Shared\FileSystem;

/**
 * Main container when a file is uploaded in parts.
 */
final readonly class ChunkFile
{
    public function __construct(
        private File $file,
        private int $currentPart,
        private int $totalParts,
        private string $identifier,
    ) {
    }

    public function getFile(): File
    {
        return $this->file;
    }

    public function getCurrentPart(): int
    {
        return $this->currentPart;
    }

    public function getTotalParts(): int
    {
        return $this->totalParts;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }
}
