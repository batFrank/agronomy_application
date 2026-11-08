<?php

declare(strict_types=1);

namespace App\Domain\ApiKey;

use App\Domain\ApiKey\Exception\ApiKeyNotFoundException;
use App\Domain\Shared\Exception\InfrastructureException;

interface ApiKeyRepository
{
    /**
     * @throws InfrastructureException
     */
    public function put(ApiKey $apiKey): void;

    /**
     * @throws InfrastructureException
     * @throws ApiKeyNotFoundException
     */
    public function findOneBy(ApiKeyCriteria $criteria): ApiKey;
}
