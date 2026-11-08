<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Persistence\ApiKey;

use App\Domain\ApiKey\ApiKeyRepository;
use App\Infrastructure\Persistence\ApiKey\InMemoryApiKeyRepository;
use App\Tests\Domain\ApiKey\ApiKeyRepositoryTestCase;

class InMemoryApiKeyRepositoryTest extends ApiKeyRepositoryTestCase
{
    protected function getRepository(): ApiKeyRepository
    {
        return new InMemoryApiKeyRepository();
    }
}
