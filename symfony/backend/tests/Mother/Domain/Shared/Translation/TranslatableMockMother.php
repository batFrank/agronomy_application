<?php

declare(strict_types=1);

namespace App\Tests\Mother\Domain\Shared\Translation;

use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Shared\Uuid\Uuid;
use App\Tests\Domain\Shared\Translation\TranslatableMock;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;

final class TranslatableMockMother
{
    private const string DEFAULT_NAME = 'Default name';
    private const string DEFAULT_DESCRIPTION = 'Default description';

    /**
     * @throws InvalidUuidException
     */
    public static function create(
        ?Uuid $id = null,
        ?string $name = null,
        ?string $description = null,
    ): TranslatableMock {
        return new TranslatableMock(
            id: $id ?? UuidMother::create(),
            name: $name ?? self::DEFAULT_NAME,
            description: $description ?? self::DEFAULT_DESCRIPTION,
        );
    }
}
