<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Transformer;

use App\Infrastructure\Transformer\DateTimeTransformer;
use PHPUnit\Framework\TestCase;

class DateTimeTransformerTest extends TestCase
{
    public function testToOutputFormatsDateTimeWithExpectedFormat(): void
    {
        $dateTime = new \DateTimeImmutable('2025-03-09 14:05:07');

        $result = DateTimeTransformer::toOutput($dateTime);

        $this->assertSame('2025-03-09 14:05:07', $result);
    }

    /**
     * @throws \DateMalformedStringException
     */
    public function testFromInputTransformsStandardFormat(): void
    {
        $result = DateTimeTransformer::fromInput('2025-03-09 14:05:07');

        $this->assertInstanceOf(\DateTimeImmutable::class, $result);
        $this->assertSame('2025-03-09 14:05:07', $result->format(DateTimeTransformer::DATE_TIME_FORMAT));
    }

    /**
     * @throws \DateMalformedStringException
     */
    public function testFromInputParsesNonStandardDateTimeString(): void
    {
        $result = DateTimeTransformer::fromInput('2025-03-09T14:05:07+00:00');

        $this->assertInstanceOf(\DateTimeImmutable::class, $result);
        $this->assertSame('2025-03-09 14:05:07', $result->setTimezone(new \DateTimeZone('UTC'))->format(DateTimeTransformer::DATE_TIME_FORMAT));
    }

    /**
     * @throws \DateMalformedStringException
     */
    public function testFromInputThrowsExceptionForInvalidDateTime(): void
    {
        $this->expectException(\Exception::class);

        DateTimeTransformer::fromInput('not-a-date-time');
    }
}
