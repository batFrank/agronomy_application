<?php

declare(strict_types=1);

namespace App\Domain\Shared\EntityDescriptor;

readonly class EntityDescriptor
{
    public function __construct(
        private string $name,
        private ?string $description = null,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }
}
