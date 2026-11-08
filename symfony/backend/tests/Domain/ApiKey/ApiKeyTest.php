<?php

declare(strict_types=1);

namespace App\Tests\Domain\ApiKey;

use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Tests\Mother\Domain\ApiKey\ApiKeyMother;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;
use PHPUnit\Framework\TestCase;

class ApiKeyTest extends TestCase
{
    /**
     * @throws InvalidUuidException
     */
    public function testApiKeyCreationWithoutUserId(): void
    {
        $apiKey = ApiKeyMother::create(userId: null, apiKey: 'clear-text-key');

        $this->assertSame('clear-text-key', $apiKey->getApiKey());
        $this->assertNull($apiKey->getUserId());
        $this->assertTrue($apiKey->isActive());
    }

    /**
     * @throws InvalidUuidException
     */
    public function testApiKeyCreationWithUserId(): void
    {
        $userId = UuidMother::create();
        $apiKey = ApiKeyMother::create(userId: $userId);

        $this->assertNotNull($apiKey->getUserId());
        $this->assertTrue($apiKey->getUserId()->equals($userId));
    }
}
