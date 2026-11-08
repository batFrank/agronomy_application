<?php

declare(strict_types=1);

namespace App\Domain\ApiKey;

use App\Domain\Shared\Entity\EntityWithIdCollection;

/**
 * @extends EntityWithIdCollection<ApiKey>
 */
class ApiKeyCollection extends EntityWithIdCollection
{
    public function getTypeName(): string
    {
        return ApiKey::class;
    }
}
