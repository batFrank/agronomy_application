<?php

declare(strict_types=1);

namespace App\Tests\Domain\Shared\Translation;

use App\Domain\Shared\Collection\CollectionException;
use App\Domain\Shared\Entity\EntityWithId;
use App\Domain\Shared\Translation\Translatable;
use App\Domain\Shared\Translation\TranslatableCollection;
use App\Domain\Shared\Uuid\Uuid;

class TranslatableMock extends EntityWithId implements Translatable
{
    public const array TRANSLATABLE_FIELDS = ['name', 'description'];
    public const array TRANSLATABLE_DEPENDENCIES = ['translatableMockCollection'];

    private TranslatableCollection $translatableMockCollection;

    /**
     * @throws CollectionException
     */
    public function __construct(
        Uuid $id,
        private string $name,
        private string $description,
    ) {
        $this->translatableMockCollection = new TranslatableMockCollection([]);

        parent::__construct($id);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public static function getEntityName(): string
    {
        return self::class;
    }

    public static function getTranslatableFields(): array
    {
        return self::TRANSLATABLE_FIELDS;
    }

    public static function getTranslatableDependencies(): array
    {
        return self::TRANSLATABLE_DEPENDENCIES;
    }

    public function setTranslation(string $field, string $value): void
    {
        if (!\in_array($field, self::getTranslatableFields())) {
            throw new \InvalidArgumentException("Invalid field: $field");
        }
        $this->$field = $value;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getTranslatableMockCollection(): TranslatableCollection
    {
        return $this->translatableMockCollection;
    }

    public function setTranslatableMockCollection(TranslatableCollection $translatableMockCollection): void
    {
        $this->translatableMockCollection = $translatableMockCollection;
    }
}
