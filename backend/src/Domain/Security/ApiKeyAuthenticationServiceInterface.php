<?php

declare(strict_types=1);

namespace App\Domain\Security;

use App\Domain\Shared\Exception\InfrastructureException;

interface ApiKeyAuthenticationServiceInterface
{
    /**
     * @throws ApiKeyAuthenticationException
     * @throws InfrastructureException
     */
    public function authenticate(?string $apiKey): void;
}
