<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Security;

use App\Domain\ApiKey\ApiKeyCriteria;
use App\Domain\ApiKey\ApiKeyRepository;
use App\Domain\ApiKey\Exception\ApiKeyNotFoundException;
use App\Domain\Security\ApiKeyAuthenticationException;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Infrastructure\Security\ApiKeyAuthenticationService;
use App\Tests\Mother\Domain\ApiKey\ApiKeyMother;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

class ApiKeyAuthenticationServiceTest extends TestCase
{
    /**
     * @throws Exception
     */
    private function getService(?ApiKeyRepository $apiKeyRepository = null): ApiKeyAuthenticationService
    {
        return new ApiKeyAuthenticationService(
            $apiKeyRepository ?? $this->createMock(ApiKeyRepository::class),
        );
    }

    /**
     * @throws Exception
     */
    public function testAuthenticateThrowsExceptionWhenApiKeyIsMissing(): void
    {
        $apiKeyRepository = $this->createMock(ApiKeyRepository::class);
        $apiKeyRepository->expects($this->never())->method('findOneBy');

        $service = $this->getService($apiKeyRepository);

        $this->expectException(ApiKeyAuthenticationException::class);
        $this->expectExceptionMessage('Missing API key.');

        $service->authenticate(null);
    }

    /**
     * @throws Exception
     */
    public function testAuthenticateThrowsExceptionWhenApiKeyIsBlank(): void
    {
        $apiKeyRepository = $this->createMock(ApiKeyRepository::class);
        $apiKeyRepository->expects($this->never())->method('findOneBy');

        $service = $this->getService($apiKeyRepository);

        $this->expectException(ApiKeyAuthenticationException::class);
        $this->expectExceptionMessage('Missing API key.');

        $service->authenticate('   ');
    }

    /**
     * @throws Exception
     * @throws InfrastructureException
     */
    public function testAuthenticateThrowsExceptionWhenApiKeyIsNotFound(): void
    {
        $apiKeyRepository = $this->createMock(ApiKeyRepository::class);
        $apiKeyRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willThrowException(new ApiKeyNotFoundException());

        $service = $this->getService($apiKeyRepository);

        $this->expectException(ApiKeyAuthenticationException::class);
        $this->expectExceptionMessage('Invalid API key.');

        $service->authenticate('missing-key');
    }

    /**
     * @throws Exception
     * @throws InfrastructureException
     * @throws InvalidUuidException
     */
    public function testAuthenticateThrowsExceptionWhenApiKeyIsInactive(): void
    {
        $apiKeyRepository = $this->createMock(ApiKeyRepository::class);
        $apiKeyRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn(ApiKeyMother::create(active: false));

        $service = $this->getService($apiKeyRepository);

        $this->expectException(ApiKeyAuthenticationException::class);
        $this->expectExceptionMessage('Inactive API key.');

        $service->authenticate('inactive-key');
    }

    /**
     * @throws Exception
     * @throws InfrastructureException
     * @throws InvalidUuidException
     */
    public function testAuthenticateSucceedsWhenApiKeyIsActiveAndHasNullUserId(): void
    {
        $apiKeyRepository = $this->createMock(ApiKeyRepository::class);
        $apiKeyRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn(ApiKeyMother::create(active: true, userId: null));

        $service = $this->getService($apiKeyRepository);

        $service->authenticate('valid-key');
    }

    /**
     * @throws Exception
     * @throws InfrastructureException
     * @throws InvalidUuidException
     */
    public function testAuthenticateSucceedsWhenApiKeyIsActiveAndHasUserId(): void
    {
        $apiKeyRepository = $this->createMock(ApiKeyRepository::class);
        $apiKeyRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn(ApiKeyMother::create(active: true, userId: UuidMother::create()));

        $service = $this->getService($apiKeyRepository);

        $service->authenticate('valid-key');
    }

    /**
     * @throws Exception
     * @throws InfrastructureException
     * @throws InvalidUuidException
     */
    public function testAuthenticateQueriesRepositoryUsingProvidedApiKey(): void
    {
        $apiKeyRepository = $this->createMock(ApiKeyRepository::class);
        $apiKeyRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with($this->callback(function (ApiKeyCriteria $criteria): bool {
                return 'expected-key' === $criteria->getKey();
            }))
            ->willReturn(ApiKeyMother::create(active: true));

        $service = $this->getService($apiKeyRepository);

        $service->authenticate('expected-key');
    }
}
