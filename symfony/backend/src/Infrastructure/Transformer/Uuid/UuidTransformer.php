<?php

declare(strict_types=1);

namespace App\Infrastructure\Transformer\Uuid;

use App\Domain\Shared\Collection\CollectionException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Shared\Uuid\Uuid;
use App\Domain\Shared\Uuid\UuidCollection;

class UuidTransformer
{
    /**
     * @param array<string> $data
     *
     * @throws InvalidUuidException
     * @throws CollectionException
     */
    public static function fromArrayOfStrings(array $data): UuidCollection
    {
        $ids = [];
        foreach ($data as $id) {
            $ids[] = new Uuid($id);
        }

        return new UuidCollection($ids);
    }
}
