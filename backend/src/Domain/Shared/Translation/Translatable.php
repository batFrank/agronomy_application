<?php

declare(strict_types=1);

namespace App\Domain\Shared\Translation;

use App\Domain\Shared\Uuid\Uuid;

interface Translatable
{
    public function getId(): Uuid;

    public static function getEntityName(): string;

    /**
     * @return array<string>
     */
    public static function getTranslatableFields(): array;

    /**
     * @return array<string>
     */
    public static function getTranslatableDependencies(): array;

    /**
     * @throws \InvalidArgumentException
     */
    public function setTranslation(string $field, string $value): void;
}
