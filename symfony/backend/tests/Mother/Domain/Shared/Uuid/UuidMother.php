<?php

declare(strict_types=1);

namespace App\Tests\Mother\Domain\Shared\Uuid;

use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Shared\Uuid\Uuid;
use Ramsey\Uuid\Uuid as RamseyUuid;

class UuidMother
{
    /**
     * @throws InvalidUuidException
     */
    public static function create(): Uuid
    {
        return new Uuid(RamseyUuid::uuid7()->toString());
    }
}
