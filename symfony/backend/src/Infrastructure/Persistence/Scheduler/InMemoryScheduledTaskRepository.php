<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Scheduler;

use App\Domain\Scheduler\Exception\ScheduledTaskNotFoundException;
use App\Domain\Scheduler\ScheduledTask;
use App\Domain\Scheduler\ScheduledTaskCollection;
use App\Domain\Scheduler\ScheduledTaskCriteria;
use App\Domain\Scheduler\ScheduledTaskRepository;
use App\Domain\Shared\Collection\CollectionException;
use App\Infrastructure\Persistence\InMemoryCommonCriteriaBuilder;

class InMemoryScheduledTaskRepository implements ScheduledTaskRepository
{
    private ScheduledTaskCollection $collection;

    /**
     * @throws CollectionException
     */
    public function __construct()
    {
        $this->collection = new ScheduledTaskCollection([]);
    }

    #[\Override]
    public function put(ScheduledTask $scheduledTask): void
    {
        try {
            $existing = $this->findOneBy(ScheduledTaskCriteria::createById($scheduledTask->getId()));
            $this->collection = $this->collection->filter(
                fn (ScheduledTask $task): bool => !$task->getId()->equals($existing->getId())
            );
        } catch (ScheduledTaskNotFoundException) {
            // Task doesn't exist, will be added
        }

        $this->collection->append($scheduledTask);
    }

    #[\Override]
    public function findOneBy(ScheduledTaskCriteria $criteria): ScheduledTask
    {
        $tasks = $this->filterByCriteria($criteria);

        if (null === $tasks->first()) {
            throw new ScheduledTaskNotFoundException();
        }

        return $tasks->first();
    }

    #[\Override]
    public function findBy(ScheduledTaskCriteria $criteria): ScheduledTaskCollection
    {
        return $this->filterByCriteria($criteria);
    }

    #[\Override]
    public function countBy(ScheduledTaskCriteria $criteria): int
    {
        return $this->filterByCriteria(
            criteria: $criteria,
            onlyFilter: true,
        )->count();
    }

    /**
     * @throws CollectionException
     */
    private function filterByCriteria(ScheduledTaskCriteria $criteria, bool $onlyFilter = false): ScheduledTaskCollection
    {
        $collection = $this->collection->filter(
            fn (ScheduledTask $task): bool => (
                null === $task->getDeletedAt()
            ) && (
                null === $criteria->getEnabled() || $task->isEnabled() === $criteria->getEnabled()
            ) && (
                null === $criteria->getName() || $task->getName() === $criteria->getName()
            )
        );

        $collection = InMemoryCommonCriteriaBuilder::filterByCommonCriteria(
            criteria: $criteria,
            collection: $collection,
            onlyFilter: $onlyFilter,
        );

        return $collection->map(
            fn (ScheduledTask $task) => new ScheduledTask(
                id: $task->getId(),
                name: $task->getName(),
                frequency: $task->getFrequency(),
                messageClass: $task->getMessageClass(),
                enabled: $task->isEnabled(),
                createdAt: $task->getCreatedAt(),
                updatedAt: $task->getUpdatedAt(),
                deletedAt: $task->getDeletedAt(),
            )
        );
    }
}
