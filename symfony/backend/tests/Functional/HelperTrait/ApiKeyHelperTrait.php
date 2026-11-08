<?php

declare(strict_types=1);

namespace App\Tests\Functional\HelperTrait;

use App\Domain\ApiKey\ApiKey;
use App\Domain\ApiKey\ApiKeyRepository;
use App\Domain\Shared\Uuid\Uuid;
use App\Tests\Mother\Domain\ApiKey\ApiKeyMother;

trait ApiKeyHelperTrait
{
    protected function getApiKeyRepository(): object
    {
        return $this->getContainer()->get(ApiKeyRepository::class);
    }

    protected function setAndGetApiKeyInRepository(
        ?Uuid $id = null,
        ?string $apiKey = null,
        ?bool $active = null,
        ?Uuid $userId = null,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null,
        ?\DateTimeImmutable $deletedAt = null,
    ): ApiKey {
        $apiKey = ApiKeyMother::create(
            id: $id,
            apiKey: $apiKey,
            active: $active,
            userId: $userId,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            deletedAt: $deletedAt,
        );

        /** @var ApiKeyRepository $repository */
        $repository = $this->getApiKeyRepository();
        $repository->put($apiKey);

        return $apiKey;
    }
}
