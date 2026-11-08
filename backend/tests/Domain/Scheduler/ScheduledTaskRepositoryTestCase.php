<?php

declare(strict_types=1);

namespace App\Tests\Domain\Scheduler;

use App\Domain\Scheduler\Exception\ScheduledTaskNotFoundException;
use App\Domain\Scheduler\ScheduledTaskCollection;
use App\Domain\Scheduler\ScheduledTaskCriteria;
use App\Domain\Scheduler\ScheduledTaskRepository;
use App\Domain\Shared\Collection\CollectionException;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Tests\Mother\Domain\Scheduler\ScheduledTaskMother;
use PHPUnit\Framework\TestCase;

abstract class ScheduledTaskRepositoryTestCase extends TestCase
{
    abstract protected function getRepository(): ScheduledTaskRepository;

    /**
     * @throws InfrastructureException
     * @throws InvalidUuidException
     * @throws ScheduledTaskNotFoundException
     */
    public function testPutAndFindOneBy(): void
    {
        $repository = $this->getRepository();
        $task = ScheduledTaskMother::create();

        $repository->put($task);

        $this->assertEquals(
            $task,
            $repository->findOneBy(
                ScheduledTaskCriteria::createById($task->getId())
            )
        );
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidUuidException
     * @throws ScheduledTaskNotFoundException
     */
    public function testFindOneByThrowsExceptionWhenNotFound(): void
    {
        $this->expectException(ScheduledTaskNotFoundException::class);

        $repository = $this->getRepository();
        $task = ScheduledTaskMother::create();

        $repository->findOneBy(
            ScheduledTaskCriteria::createById($task->getId())
        );
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidUuidException
     * @throws CollectionException
     */
    public function testFindBy(): void
    {
        $task1 = ScheduledTaskMother::create(
            name: 'Task 1',
            enabled: true,
        );
        $task2 = ScheduledTaskMother::create(
            name: 'Task 2',
            enabled: false,
        );

        $repository = $this->getRepository();
        $repository->put($task1);
        $repository->put($task2);

        $this->assertCount(
            2,
            $repository->findBy(
                ScheduledTaskCriteria::createEmpty()
            )->all()
        );

        $this->assertEquals(
            new ScheduledTaskCollection([$task1]),
            $repository->findBy(
                ScheduledTaskCriteria::createEmpty()
                    ->filterByEnabled(true)
            )
        );

        $this->assertEquals(
            new ScheduledTaskCollection([$task2]),
            $repository->findBy(
                ScheduledTaskCriteria::createEmpty()
                    ->filterByEnabled(false)
            )
        );
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidUuidException
     */
    public function testFindByName(): void
    {
        $name = 'Specific Task Name';
        $task = ScheduledTaskMother::create(name: $name);

        $repository = $this->getRepository();
        $repository->put($task);
        $repository->put(ScheduledTaskMother::create());

        $this->assertCount(
            1,
            $repository->findBy(
                ScheduledTaskCriteria::createEmpty()
                    ->filterByName($name)
            )->all()
        );
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidUuidException
     */
    public function testCountBy(): void
    {
        $repository = $this->getRepository();
        $repository->put(ScheduledTaskMother::create(enabled: true));
        $repository->put(ScheduledTaskMother::create(enabled: false));
        $repository->put(ScheduledTaskMother::create(enabled: true));

        $this->assertEquals(
            3,
            $repository->countBy(ScheduledTaskCriteria::createEmpty())
        );

        $this->assertEquals(
            2,
            $repository->countBy(
                ScheduledTaskCriteria::createEmpty()
                    ->filterByEnabled(true)
            )
        );
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidUuidException
     * @throws ScheduledTaskNotFoundException
     */
    public function testPutUpdatesExistingTask(): void
    {
        $repository = $this->getRepository();
        $task = ScheduledTaskMother::create(enabled: true);

        $repository->put($task);
        $task->disable();
        $repository->put($task);

        $foundTask = $repository->findOneBy(
            ScheduledTaskCriteria::createById($task->getId())
        );

        $this->assertFalse($foundTask->isEnabled());
    }
}
