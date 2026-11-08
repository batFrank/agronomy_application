<?php

declare(strict_types=1);

namespace App\Tests\Domain\Shared\Url;

use App\Domain\Shared\Exception\DomainException;
use App\Domain\Shared\Url\InvalidUrlException;
use App\Domain\Shared\Url\Url;
use App\Tests\Mother\Domain\Shared\Url\UrlMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UrlTest extends TestCase
{
    #[DataProvider('invalidUrlProvider')]
    public function testInvalidUrlThrowsInvalidUrlException(string $invalidUrl): void
    {
        $this->expectException(InvalidUrlException::class);

        new Url($invalidUrl);
    }

    public function testInvalidUrlExceptionExtendsDomainException(): void
    {
        $exception = new InvalidUrlException('not-a-url');

        $this->assertInstanceOf(DomainException::class, $exception);
    }

    /**
     * @throws InvalidUrlException
     */
    public function testCreateValidUrl(): void
    {
        $url = UrlMother::create('https://example.com/test');

        $this->assertSame('https://example.com/test', $url->value());
        $this->assertSame('https://example.com/test', (string) $url);
    }

    /**
     * @throws InvalidUrlException
     */
    public function testEquals(): void
    {
        $url1 = UrlMother::create('https://example.com');
        $url2 = UrlMother::create('https://example.com');
        $url3 = UrlMother::create('https://other.com');

        $this->assertTrue($url1->equals($url2));
        $this->assertFalse($url1->equals($url3));
    }

    public static function invalidUrlProvider(): \Generator
    {
        yield 'random string' => ['not-a-url'];
        yield 'missing protocol' => ['www.example.com'];
        yield 'empty string' => [''];
        yield 'spaces in url' => ['https://example .com'];
    }
}
