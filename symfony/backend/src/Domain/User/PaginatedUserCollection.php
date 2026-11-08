<?php

declare(strict_types=1);

namespace App\Domain\User;

use App\Domain\Shared\Collection\PaginatedCollection;

/**
 * @extends PaginatedCollection<User>
 */
class PaginatedUserCollection extends PaginatedCollection
{
    public function __construct(
        private readonly UserCollection $collection,
        int $totalItems,
    ) {
        parent::__construct($totalItems);
    }

    public function getCollection(): UserCollection
    {
        return $this->collection;
    }
}
