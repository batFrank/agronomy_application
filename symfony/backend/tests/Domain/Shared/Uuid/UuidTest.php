<?php

declare(strict_types=1);

namespace App\Tests\Domain\Shared\Uuid;

use App\Domain\Shared\Exception\DomainException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Shared\Uuid\Uuid;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UuidTest extends TestCase
{
    #[DataProvider('invalidUuidProvider')]
    public function testInvalidUuidThrowsInvalidUuidException(string $invalidUuid): void
    {
        $this->expectException(InvalidUuidException::class);

        new Uuid($invalidUuid);
    }

    public function testInvalidUuidExceptionExtendsDomainException(): void
    {
        $exception = new InvalidUuidException('not-a-uuid');

        $this->assertInstanceOf(DomainException::class, $exception);
    }

    /**
     * @throws InvalidUuidException
     */
    public function testCreateValidUuid(): void
    {
        $uuid = UuidMother::create();

        $this->assertNotEmpty($uuid->value());
        $this->assertSame($uuid->value(), (string) $uuid);
    }

    /**
     * @throws InvalidUuidException
     */
    public function testEquals(): void
    {
        $uuid1 = UuidMother::create();
        $uuid2 = new Uuid($uuid1->value());
        $uuid3 = UuidMother::create();

        $this->assertTrue($uuid1->equals($uuid2));
        $this->assertFalse($uuid1->equals($uuid3));
    }

    /**
     * @throws InvalidUuidException
     */
    public function testEqualsIsCaseInsensitive(): void
    {
        $uuid = UuidMother::create();
        $uppercase = new Uuid(mb_strtoupper($uuid->value()));

        $this->assertTrue($uuid->equals($uppercase));
        $this->assertTrue($uppercase->equals($uuid));
    }

    public static function invalidUuidProvider(): \Generator
    {
        yield 'random string' => ['not-a-uuid'];
        yield 'uuid v4 (not v7)' => ['a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11'];
        yield 'empty string' => [''];
        yield 'partial uuid' => ['018f4b00-1111-7111'];
    }
}
