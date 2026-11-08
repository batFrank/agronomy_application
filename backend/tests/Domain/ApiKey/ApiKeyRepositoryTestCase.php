<?php

declare(strict_types=1);

namespace App\Tests\Domain\ApiKey;

use App\Domain\ApiKey\ApiKeyCriteria;
use App\Domain\ApiKey\ApiKeyRepository;
use App\Domain\ApiKey\Exception\ApiKeyNotFoundException;
use App\Domain\ApiKey\Exception\ApiKeyRepositoryException;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Tests\Mother\Domain\ApiKey\ApiKeyMother;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;
use PHPUnit\Framework\TestCase;

abstract class ApiKeyRepositoryTestCase extends TestCase
{
    abstract protected function getRepository(): ApiKeyRepository;

    /**
     * @throws InfrastructureException
     * @throws InvalidUuidException
     * @throws ApiKeyNotFoundException
     */
    public function testPutAndFindOneBy(): void
    {
        $repository = $this->getRepository();
        $apiKey = ApiKeyMother::create();

        $repository->put($apiKey);

        $this->assertEquals(
            $apiKey,
            $repository->findOneBy(ApiKeyCriteria::createById($apiKey->getId()))
        );
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidUuidException
     */
    public function testFindOneByThrowsExceptionWhenNotFound(): void
    {
        $this->expectException(ApiKeyNotFoundException::class);

        $repository = $this->getRepository();
        $apiKey = ApiKeyMother::create();

        $repository->findOneBy(ApiKeyCriteria::createById($apiKey->getId()));
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidUuidException
     * @throws ApiKeyNotFoundException
     */
    public function testFindOneByKey(): void
    {
        $repository = $this->getRepository();

        $first = ApiKeyMother::create(apiKey: 'first-key');
        $second = ApiKeyMother::create(apiKey: 'second-key');

        $repository->put($first);
        $repository->put($second);

        $this->assertEquals(
            $second,
            $repository->findOneBy(ApiKeyCriteria::createEmpty()->filterByKey('second-key'))
        );
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidUuidException
     * @throws ApiKeyNotFoundException
     */
    public function testPutUpdatesExistingApiKey(): void
    {
        $repository = $this->getRepository();
        $original = ApiKeyMother::create(apiKey: 'old-key', active: true);

        $repository->put($original);

        $updated = ApiKeyMother::create(
            id: $original->getId(),
            apiKey: 'new-key',
            active: false,
            createdAt: $original->getCreatedAt(),
        );
        $repository->put($updated);

        $found = $repository->findOneBy(ApiKeyCriteria::createById($original->getId()));

        $this->assertSame('new-key', $found->getApiKey());
        $this->assertFalse($found->isActive());
        $this->assertNotNull($found->getUpdatedAt());
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidUuidException
     */
    public function testPutThrowsExceptionWhenApiKeyIsDuplicated(): void
    {
        $this->expectException(ApiKeyRepositoryException::class);

        $repository = $this->getRepository();

        $repository->put(ApiKeyMother::create(apiKey: 'duplicated-key'));
        $repository->put(ApiKeyMother::create(apiKey: 'duplicated-key'));
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidUuidException
     */
    public function testFindOneByIgnoresSoftDeletedRows(): void
    {
        $this->expectException(ApiKeyNotFoundException::class);

        $repository = $this->getRepository();
        $deletedApiKey = ApiKeyMother::create(deletedAt: new \DateTimeImmutable());
        $repository->put($deletedApiKey);

        $repository->findOneBy(ApiKeyCriteria::createById($deletedApiKey->getId()));
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidUuidException
     * @throws ApiKeyNotFoundException
     */
    public function testPutAndFindOneByWithOptionalUserId(): void
    {
        $repository = $this->getRepository();
        $userId = UuidMother::create();

        $apiKey = ApiKeyMother::create(userId: $userId);
        $repository->put($apiKey);

        $found = $repository->findOneBy(ApiKeyCriteria::createById($apiKey->getId()));

        $this->assertNotNull($found->getUserId());
        $this->assertTrue($found->getUserId()->equals($userId));
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidUuidException
     * @throws ApiKeyNotFoundException
     */
    public function testPutAndFindOneByWithNullUserId(): void
    {
        $repository = $this->getRepository();
        $apiKey = ApiKeyMother::create(userId: null);

        $repository->put($apiKey);

        $found = $repository->findOneBy(ApiKeyCriteria::createById($apiKey->getId()));

        $this->assertNull($found->getUserId());
    }
}
