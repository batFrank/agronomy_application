<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Scheduler;

use App\Domain\Scheduler\Exception\ScheduledTaskNotFoundException;
use App\Domain\Scheduler\Exception\ScheduledTaskRepositoryException;
use App\Domain\Scheduler\ScheduledTask;
use App\Domain\Scheduler\ScheduledTaskCollection;
use App\Domain\Scheduler\ScheduledTaskCriteria;
use App\Domain\Scheduler\ScheduledTaskRepository;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Shared\Uuid\Uuid;
use App\Infrastructure\Persistence\DBALCommonCriteriaBuilder;
use App\Infrastructure\Persistence\DBALDateTimeFormatter;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Query\QueryBuilder;

readonly class DBALScheduledTaskRepository implements ScheduledTaskRepository
{
    public function __construct(
        private Connection $connection,
        private string $scheduledTaskTableName,
    ) {
    }

    /**
     * @throws InfrastructureException
     */
    #[\Override]
    public function put(ScheduledTask $scheduledTask): void
    {
        try {
            $this->findOneBy(ScheduledTaskCriteria::createById($scheduledTask->getId()));

            $this->update($scheduledTask);
        } catch (ScheduledTaskNotFoundException) {
            $this->insert($scheduledTask);
        }
    }

    #[\Override]
    public function findOneBy(ScheduledTaskCriteria $criteria): ScheduledTask
    {
        try {
            $taskArray = $this
                ->getQueryBuilderByCriteria($criteria)
                ->executeQuery()
                ->fetchAssociative();

            if (false === $taskArray) {
                throw new ScheduledTaskNotFoundException();
            }

            return $this->fromArrayToScheduledTask($taskArray);
        } catch (DBALException|InvalidUuidException $e) {
            throw InfrastructureException::fromPrevious($e);
        }
    }

    #[\Override]
    public function findBy(ScheduledTaskCriteria $criteria): ScheduledTaskCollection
    {
        try {
            $tasksArray = $this
                ->getQueryBuilderByCriteria($criteria)
                ->executeQuery()
                ->fetchAllAssociative();

            return new ScheduledTaskCollection(
                array_map(
                    fn (array $taskArray) => $this->fromArrayToScheduledTask($taskArray),
                    $tasksArray
                )
            );
        } catch (DBALException|InvalidUuidException $e) {
            throw InfrastructureException::fromPrevious($e);
        }
    }

    #[\Override]
    public function countBy(ScheduledTaskCriteria $criteria): int
    {
        try {
            return $this
                ->getQueryBuilderByCriteria(
                    criteria: $criteria,
                    onlyFilter: true,
                )
                ->select('COUNT(t.id)')
                ->executeQuery()
                ->fetchOne();
        } catch (DBALException $e) {
            throw InfrastructureException::fromPrevious($e);
        }
    }

    /**
     * @throws ScheduledTaskRepositoryException
     */
    private function insert(ScheduledTask $scheduledTask): void
    {
        try {
            $this->connection->insert(
                table: $this->scheduledTaskTableName,
                data: $this->fromScheduledTaskToArray($scheduledTask)
            );
        } catch (DBALException $e) {
            throw ScheduledTaskRepositoryException::fromPrevious($e);
        }
    }

    /**
     * @throws ScheduledTaskNotFoundException
     * @throws ScheduledTaskRepositoryException
     */
    private function update(ScheduledTask $scheduledTask): void
    {
        try {
            $data = $this->fromScheduledTaskToArray($scheduledTask);
            unset($data['id']);

            $result = $this->connection->update(
                table: $this->scheduledTaskTableName,
                data: $data,
                criteria: ['id' => $scheduledTask->getId()->value()]
            );

            if (0 === $result) {
                throw new ScheduledTaskNotFoundException();
            }
        } catch (DBALException $e) {
            throw ScheduledTaskRepositoryException::fromPrevious($e);
        }
    }

    /**
     * @param array<string, mixed> $taskArray
     *
     * @throws InvalidUuidException
     * @throws ScheduledTaskRepositoryException
     */
    private function fromArrayToScheduledTask(array $taskArray): ScheduledTask
    {
        $createdAt = DBALDateTimeFormatter::parse($taskArray['created_at']);
        if (null === $createdAt) {
            throw new ScheduledTaskRepositoryException('Invalid created_at format');
        }

        return new ScheduledTask(
            id: new Uuid($taskArray['id']),
            name: $taskArray['name'],
            frequency: $taskArray['frequency'],
            messageClass: $taskArray['message_class'],
            enabled: (bool) $taskArray['enabled'],
            createdAt: $createdAt,
            updatedAt: DBALDateTimeFormatter::parse($taskArray['updated_at']),
            deletedAt: DBALDateTimeFormatter::parse($taskArray['deleted_at']),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function fromScheduledTaskToArray(ScheduledTask $scheduledTask): array
    {
        return [
            'id' => $scheduledTask->getId()->value(),
            'name' => $scheduledTask->getName(),
            'frequency' => $scheduledTask->getFrequency(),
            'message_class' => $scheduledTask->getMessageClass(),
            'enabled' => $scheduledTask->isEnabled() ? 1 : 0,
            'created_at' => DBALDateTimeFormatter::format($scheduledTask->getCreatedAt()),
            'updated_at' => DBALDateTimeFormatter::format($scheduledTask->getUpdatedAt()),
            'deleted_at' => DBALDateTimeFormatter::format($scheduledTask->getDeletedAt()),
        ];
    }

    private function getQueryBuilderByCriteria(
        ScheduledTaskCriteria $criteria,
        bool $onlyFilter = false,
    ): QueryBuilder {
        $queryBuilder = $this->connection->createQueryBuilder();

        $queryBuilder
            ->select('t.*')
            ->from($this->scheduledTaskTableName, 't')
            ->andWhere('t.deleted_at IS NULL');

        if (null !== $criteria->getEnabled()) {
            $queryBuilder
                ->andWhere('t.enabled = :enabled')
                ->setParameter('enabled', $criteria->getEnabled() ? 1 : 0);
        }

        if (null !== $criteria->getName()) {
            $queryBuilder
                ->andWhere('t.name = :name')
                ->setParameter('name', $criteria->getName());
        }

        DBALCommonCriteriaBuilder::filterByCommonCriteria($criteria, $queryBuilder, $onlyFilter);

        return $queryBuilder;
    }
}
