<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\ApiKey;

use App\Domain\ApiKey\ApiKey;
use App\Domain\ApiKey\ApiKeyCriteria;
use App\Domain\ApiKey\ApiKeyRepository;
use App\Domain\ApiKey\Exception\ApiKeyNotFoundException;
use App\Domain\ApiKey\Exception\ApiKeyRepositoryException;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Shared\Uuid\Uuid;
use App\Infrastructure\Persistence\DBALCommonCriteriaBuilder;
use App\Infrastructure\Persistence\DBALDateTimeFormatter;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Query\QueryBuilder;

readonly class DBALApiKeyRepository implements ApiKeyRepository
{
    public function __construct(
        private Connection $connection,
        private string $apiKeyTableName,
    ) {
    }

    /**
     * @throws InfrastructureException
     */
    #[\Override]
    public function put(ApiKey $apiKey): void
    {
        try {
            $this->findOneBy(ApiKeyCriteria::createById($apiKey->getId()));
            $apiKey->markAsUpdated();
            $this->update($apiKey);
        } catch (ApiKeyNotFoundException) {
            $this->insert($apiKey);
        }
    }

    #[\Override]
    public function findOneBy(ApiKeyCriteria $criteria): ApiKey
    {
        try {
            $apiKeyData = $this
                ->getQueryBuilderByCriteria($criteria)
                ->executeQuery()
                ->fetchAssociative();

            if (false === $apiKeyData) {
                throw new ApiKeyNotFoundException();
            }

            return $this->fromArrayToApiKey($apiKeyData);
        } catch (DBALException|InvalidUuidException $e) {
            throw InfrastructureException::fromPrevious($e);
        }
    }

    /**
     * @throws ApiKeyRepositoryException
     */
    private function insert(ApiKey $apiKey): void
    {
        try {
            $this->connection->insert(
                table: $this->apiKeyTableName,
                data: $this->fromApiKeyToArray($apiKey),
            );
        } catch (UniqueConstraintViolationException) {
            throw ApiKeyRepositoryException::duplicateApiKey();
        } catch (DBALException $e) {
            throw ApiKeyRepositoryException::fromPrevious($e);
        }
    }

    /**
     * @throws ApiKeyRepositoryException
     */
    private function update(ApiKey $apiKey): void
    {
        try {
            $data = $this->fromApiKeyToArray($apiKey);
            unset($data['id']);

            $result = $this->connection->update(
                table: $this->apiKeyTableName,
                data: $data,
                criteria: ['id' => $apiKey->getId()->value()],
            );

            if (0 === $result) {
                throw new ApiKeyRepositoryException('Api key not found for update');
            }
        } catch (UniqueConstraintViolationException) {
            throw ApiKeyRepositoryException::duplicateApiKey();
        } catch (DBALException $e) {
            throw ApiKeyRepositoryException::fromPrevious($e);
        }
    }

    /**
     * @param array<string, mixed> $apiKeyData
     *
     * @throws InvalidUuidException
     * @throws ApiKeyRepositoryException
     */
    private function fromArrayToApiKey(array $apiKeyData): ApiKey
    {
        $createdAt = DBALDateTimeFormatter::parse($apiKeyData['created_at']);
        if (null === $createdAt) {
            throw new ApiKeyRepositoryException('Invalid created_at format');
        }

        return new ApiKey(
            id: new Uuid($apiKeyData['id']),
            apiKey: $apiKeyData['api_key'],
            active: (bool) $apiKeyData['active'],
            userId: null !== $apiKeyData['user_id'] ? new Uuid($apiKeyData['user_id']) : null,
            createdAt: $createdAt,
            updatedAt: DBALDateTimeFormatter::parse($apiKeyData['updated_at']),
            deletedAt: DBALDateTimeFormatter::parse($apiKeyData['deleted_at']),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function fromApiKeyToArray(ApiKey $apiKey): array
    {
        return [
            'id' => $apiKey->getId()->value(),
            'api_key' => $apiKey->getApiKey(),
            'active' => $apiKey->isActive() ? 1 : 0,
            'user_id' => $apiKey->getUserId()?->value(),
            'created_at' => DBALDateTimeFormatter::format($apiKey->getCreatedAt()),
            'updated_at' => DBALDateTimeFormatter::format($apiKey->getUpdatedAt()),
            'deleted_at' => DBALDateTimeFormatter::format($apiKey->getDeletedAt()),
        ];
    }

    private function getQueryBuilderByCriteria(ApiKeyCriteria $criteria): QueryBuilder
    {
        $queryBuilder = $this->connection->createQueryBuilder();

        $queryBuilder
            ->select('t.*')
            ->from($this->apiKeyTableName, 't')
            ->andWhere('t.deleted_at IS NULL');

        if (null !== $criteria->getKey()) {
            $queryBuilder
                ->andWhere('t.api_key = :apiKey')
                ->setParameter('apiKey', $criteria->getKey());
        }

        DBALCommonCriteriaBuilder::filterByCommonCriteria(
            criteria: $criteria,
            queryBuilder: $queryBuilder,
        );

        return $queryBuilder;
    }
}
