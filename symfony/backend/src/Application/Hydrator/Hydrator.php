<?php

declare(strict_types=1);

namespace App\Application\Hydrator;

use App\Domain\Shared\Collection\Collection;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Hydrator\HydrationCriteria;
use App\Domain\Shared\Hydrator\HydratorException;

/**
 * @template T
 */
interface Hydrator
{
    public function supports(HydrationCriteria $criteria): bool;

    public function getPriority(): HydratorPriority;

    /**
     * @param Collection<T> $collection
     *
     * @throws HydratorException
     * @throws InfrastructureException
     */
    public function hydrate(Collection $collection, HydrationCriteria $criteria): void;
}
