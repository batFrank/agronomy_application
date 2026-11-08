<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\ApiKey;

use App\Domain\ApiKey\ApiKey;
use App\Domain\ApiKey\ApiKeyCollection;
use App\Domain\ApiKey\ApiKeyCriteria;
use App\Domain\ApiKey\ApiKeyRepository;
use App\Domain\ApiKey\Exception\ApiKeyNotFoundException;
use App\Domain\ApiKey\Exception\ApiKeyRepositoryException;
use App\Domain\Shared\Collection\CollectionException;
use App\Infrastructure\Persistence\InMemoryCommonCriteriaBuilder;

class InMemoryApiKeyRepository implements ApiKeyRepository
{
    private ApiKeyCollection $collection;

    /**
     * @throws CollectionException
     */
    public function __construct()
    {
        $this->collection = new ApiKeyCollection([]);
    }

    #[\Override]
    public function put(ApiKey $apiKey): void
    {
        $duplicatedApiKeys = $this->collection->filter(
            fn (ApiKey $existingApiKey): bool => !$existingApiKey->getId()->equals($apiKey->getId())
                && $existingApiKey->getApiKey() === $apiKey->getApiKey()
        );

        if (!$duplicatedApiKeys->isEmpty()) {
            throw ApiKeyRepositoryException::duplicateApiKey();
        }

        $existingApiKeys = $this->collection->filter(
            fn (ApiKey $existingApiKey): bool => $existingApiKey->getId()->equals($apiKey->getId())
        );

        if (!$existingApiKeys->isEmpty()) {
            $apiKey->markAsUpdated();
        }

        $this->collection = $this->collection->filter(
            fn (ApiKey $repositoryApiKey): bool => !$repositoryApiKey->getId()->equals($apiKey->getId())
        );
        $this->collection->append($this->cloneApiKey($apiKey));
    }

    #[\Override]
    public function findOneBy(ApiKeyCriteria $criteria): ApiKey
    {
        $apiKeys = $this->filterByCriteria($criteria);

        if (null !== $apiKey = $apiKeys->first()) {
            return $apiKey;
        }

        throw new ApiKeyNotFoundException();
    }

    /**
     * @throws CollectionException
     */
    private function filterByCriteria(ApiKeyCriteria $criteria, bool $onlyFilter = false): ApiKeyCollection
    {
        $collection = $this->collection->filter(
            fn (ApiKey $apiKey): bool => null === $apiKey->getDeletedAt()
                && (null === $criteria->getKey() || $apiKey->getApiKey() === $criteria->getKey())
        );

        /** @var ApiKeyCollection $collection */
        $collection = InMemoryCommonCriteriaBuilder::filterByCommonCriteria(
            criteria: $criteria,
            collection: $collection,
            onlyFilter: $onlyFilter,
        );

        return $collection->map(
            fn (ApiKey $apiKey) => $this->cloneApiKey($apiKey)
        );
    }

    private function cloneApiKey(ApiKey $apiKey): ApiKey
    {
        return new ApiKey(
            id: $apiKey->getId(),
            apiKey: $apiKey->getApiKey(),
            active: $apiKey->isActive(),
            userId: $apiKey->getUserId(),
            createdAt: $apiKey->getCreatedAt(),
            updatedAt: $apiKey->getUpdatedAt(),
            deletedAt: $apiKey->getDeletedAt(),
        );
    }
}
