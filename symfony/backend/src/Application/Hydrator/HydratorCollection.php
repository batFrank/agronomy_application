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
class HydratorCollection
{
    /** @var Hydrator<T>[] */
    private array $firstPriorityHydrators = [];

    /** @var Hydrator<T>[] */
    private array $secondPriorityHydrators = [];

    /** @var Hydrator<T>[] */
    private array $thirdPriorityHydrators = [];

    /**
     * @param Hydrator<T>[] $hydrators
     */
    public function __construct(array $hydrators)
    {
        foreach ($hydrators as $hydrator) {
            match ($hydrator->getPriority()) {
                HydratorPriority::First => $this->firstPriorityHydrators[] = $hydrator,
                HydratorPriority::Second => $this->secondPriorityHydrators[] = $hydrator,
                HydratorPriority::Third => $this->thirdPriorityHydrators[] = $hydrator,
            };
        }
    }

    /**
     * @param Collection<T> $collection
     *
     * @throws HydratorException
     * @throws InfrastructureException
     */
    public function hydrate(Collection $collection, HydrationCriteria $criteria): void
    {
        foreach (HydratorPriority::getInOrder() as $priority) {
            $hydrators = $this->getHydratorsByPriority($priority);
            foreach ($hydrators as $hydrator) {
                if ($hydrator->supports($criteria)) {
                    $hydrator->hydrate($collection, $criteria);
                }
            }
        }
    }

    /**
     * @return Hydrator<T>[]
     */
    private function getHydratorsByPriority(HydratorPriority $priority): array
    {
        return match ($priority) {
            HydratorPriority::First => $this->firstPriorityHydrators,
            HydratorPriority::Second => $this->secondPriorityHydrators,
            HydratorPriority::Third => $this->thirdPriorityHydrators,
        };
    }
}
