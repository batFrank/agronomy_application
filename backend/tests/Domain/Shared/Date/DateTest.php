<?php

declare(strict_types=1);

namespace App\Tests\Domain\Shared\Date;

use App\Domain\Shared\Date\Date;
use PHPUnit\Framework\TestCase;

class DateTest extends TestCase
{
    /**
     * @throws \DateMalformedStringException
     */
    public function testFromString(): void
    {
        $dateString = '2023-01-01';
        $date = Date::fromString($dateString);

        $this->assertEquals($dateString, $date->format());
    }

    public function testFromDateTime(): void
    {
        $dateTime = new \DateTimeImmutable('2023-01-01 12:00:00');
        $date = Date::fromDateTime($dateTime);

        $this->assertEquals('2023-01-01', $date->format());
    }

    /**
     * @throws \DateMalformedStringException
     */
    public function testEquals(): void
    {
        $date1 = Date::fromString('2023-01-01');
        $date2 = Date::fromString('2023-01-01');
        $date3 = Date::fromString('2023-01-02');

        $this->assertTrue($date1->equals($date2));
        $this->assertFalse($date1->equals($date3));
    }

    public function testInvalidString(): void
    {
        $this->expectException(\DateMalformedStringException::class);

        Date::fromString('invalid');
    }

    public function testNormalization(): void
    {
        $dateTime = new \DateTimeImmutable('2023-01-01 12:00:00');
        $date = Date::fromDateTime($dateTime);

        $this->assertEquals('2023-01-01', $date->format());
    }

    /**
     * @throws \DateMalformedStringException
     */
    public function testFormat(): void
    {
        $date = Date::fromString('2023-01-01');
        $this->assertEquals('2023-01-01', $date->format());
    }

    public function testValue(): void
    {
        $dateTime = new \DateTimeImmutable('2023-01-01 12:00:00');
        $date = Date::fromDateTime($dateTime);
        $this->assertEquals($dateTime->setTime(0, 0, 0), $date->value());
    }
}
