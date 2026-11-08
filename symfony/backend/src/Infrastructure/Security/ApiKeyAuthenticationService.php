<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\ApiKey\ApiKeyCriteria;
use App\Domain\ApiKey\ApiKeyRepository;
use App\Domain\ApiKey\Exception\ApiKeyNotFoundException;
use App\Domain\Security\ApiKeyAuthenticationException;
use App\Domain\Security\ApiKeyAuthenticationServiceInterface;
use App\Domain\Shared\Exception\InfrastructureException;

readonly class ApiKeyAuthenticationService implements ApiKeyAuthenticationServiceInterface
{
    public function __construct(
        private ApiKeyRepository $apiKeyRepository,
    ) {
    }

    /**
     * @throws ApiKeyAuthenticationException
     * @throws InfrastructureException
     */
    #[\Override]
    public function authenticate(?string $apiKey): void
    {
        $apiKey = trim((string) $apiKey);
        if ('' === $apiKey) {
            throw ApiKeyAuthenticationException::missingApiKey();
        }

        try {
            $foundApiKey = $this->apiKeyRepository->findOneBy(
                ApiKeyCriteria::createEmpty()->filterByKey($apiKey)
            );
        } catch (ApiKeyNotFoundException) {
            throw ApiKeyAuthenticationException::invalidApiKey();
        }

        if (!$foundApiKey->isActive()) {
            throw ApiKeyAuthenticationException::inactiveApiKey();
        }
    }
}
