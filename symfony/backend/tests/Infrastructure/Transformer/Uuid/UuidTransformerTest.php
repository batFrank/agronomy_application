<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Transformer\Uuid;

use App\Domain\Shared\Collection\CollectionException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Infrastructure\Transformer\Uuid\UuidTransformer;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;
use PHPUnit\Framework\TestCase;

class UuidTransformerTest extends TestCase
{
    /**
     * @throws InvalidUuidException
     * @throws CollectionException
     */
    public function testFromArrayOfStringsTransformsValidUuidsKeepingOrder(): void
    {
        $firstUuid = UuidMother::create()->value();
        $secondUuid = UuidMother::create()->value();

        $result = UuidTransformer::fromArrayOfStrings([$firstUuid, $secondUuid]);

        $this->assertCount(2, $result);
        $this->assertSame([$firstUuid, $secondUuid], array_map(static fn ($uuid) => $uuid->value(), $result->all()));
    }

    /**
     * @throws InvalidUuidException
     * @throws CollectionException
     */
    public function testFromArrayOfStringsWithEmptyArrayReturnsEmptyCollection(): void
    {
        $result = UuidTransformer::fromArrayOfStrings([]);

        $this->assertCount(0, $result);
        $this->assertTrue($result->isEmpty());
    }

    /**
     * @throws CollectionException
     */
    public function testFromArrayOfStringsThrowsExceptionForInvalidUuid(): void
    {
        $this->expectException(InvalidUuidException::class);

        UuidTransformer::fromArrayOfStrings(['not-a-uuid']);
    }
}
