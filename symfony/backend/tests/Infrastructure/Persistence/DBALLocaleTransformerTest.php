<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Persistence;

use App\Domain\Shared\Exception\DomainException;
use App\Domain\Shared\Locale\InvalidLocaleException;
use App\Domain\Shared\Locale\Locale;
use App\Infrastructure\Persistence\DBALLocaleTransformer;
use PHPUnit\Framework\TestCase;

class DBALLocaleTransformerTest extends TestCase
{
    public function testFromStringToLocaleThrowsInvalidLocaleExceptionOnInvalidLocale(): void
    {
        $this->expectException(InvalidLocaleException::class);

        DBALLocaleTransformer::fromStringToLocale('invalid_locale');
    }

    public function testInvalidLocaleExceptionExtendsDomainException(): void
    {
        $exception = InvalidLocaleException::fromString('invalid');

        $this->assertInstanceOf(DomainException::class, $exception);
    }

    /**
     * @throws InvalidLocaleException
     */
    public function testFromStringToLocaleReturnsLocaleOnValidString(): void
    {
        $this->assertSame(Locale::ES, DBALLocaleTransformer::fromStringToLocale('es'));
        $this->assertSame(Locale::ES, DBALLocaleTransformer::fromStringToLocale('ES'));
        $this->assertSame(Locale::EN, DBALLocaleTransformer::fromStringToLocale('en'));
        $this->assertSame(Locale::EN, DBALLocaleTransformer::fromStringToLocale('EN'));
    }

    public function testFromLocaleToStringReturnsLowercaseString(): void
    {
        $this->assertSame('es', DBALLocaleTransformer::fromLocaleToString(Locale::ES));
        $this->assertSame('en', DBALLocaleTransformer::fromLocaleToString(Locale::EN));
    }
}
