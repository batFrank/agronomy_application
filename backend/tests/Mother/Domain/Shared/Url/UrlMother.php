<?php

declare(strict_types=1);

namespace App\Tests\Mother\Domain\Shared\Url;

use App\Domain\Shared\Url\InvalidUrlException;
use App\Domain\Shared\Url\Url;

class UrlMother
{
    private const string DEFAULT_URL = 'https://example.com';

    /**
     * @throws InvalidUrlException
     */
    public static function create(?string $url = null): Url
    {
        return new Url($url ?? self::DEFAULT_URL);
    }
}
