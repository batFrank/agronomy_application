<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Command;

use App\Domain\Scheduler\Exception\ScheduledTaskNotFoundException;
use App\Domain\Scheduler\ScheduledTaskCollection;
use App\Domain\Scheduler\ScheduledTaskCriteria;
use App\Domain\Scheduler\ScheduledTaskRepository;
use App\Domain\Shared\Collection\CollectionException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Shared\Uuid\UuidGenerator;
use App\Infrastructure\Command\ScheduledTaskCommand;
use App\Tests\Mother\Domain\Scheduler\ScheduledTaskMother;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ScheduledTaskCommandTest extends TestCase
{
    private function getCommand(
        ?ScheduledTaskRepository $scheduledTaskRepository = null,
        ?UuidGenerator $uuidGenerator = null,
    ): CommandTester {
        $scheduledTaskRepository = $scheduledTaskRepository ?? $this->createStub(ScheduledTaskRepository::class);
        $uuidGenerator = $uuidGenerator ?? $this->createStub(UuidGenerator::class);

        return new CommandTester(
            new ScheduledTaskCommand(
                $scheduledTaskRepository,
                $uuidGenerator,
            )
        );
    }

    /**
     * @throws CollectionException
     */
    public function testListWithNoTasks(): void
    {
        $scheduledTaskRepository = $this->createMock(ScheduledTaskRepository::class);
        $scheduledTaskRepository
            ->expects($this->once())
            ->method('findBy')
            ->with($this->isInstanceOf(ScheduledTaskCriteria::class))
            ->willReturn(new ScheduledTaskCollection([]));

        $command = $this->getCommand($scheduledTaskRepository);
        $exitCode = $command
            ->execute(['action' => 'list']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('No hay tareas programadas', $command->getDisplay());
    }

    /**
     * @throws InvalidUuidException
     * @throws CollectionException
     */
    public function testListWithTasks(): void
    {
        $task1 = ScheduledTaskMother::create(
            name: 'Task 1',
            frequency: '1 hour',
            messageClass: \stdClass::class,
            enabled: true,
        );
        $task2 = ScheduledTaskMother::create(
            name: 'Task 2',
            frequency: '30 minutes',
            messageClass: \stdClass::class,
            enabled: false,
        );

        $scheduledTaskRepository = $this->createMock(ScheduledTaskRepository::class);
        $scheduledTaskRepository
            ->expects($this->once())
            ->method('findBy')
            ->willReturn(new ScheduledTaskCollection([$task1, $task2]));

        $command = $this->getCommand($scheduledTaskRepository);
        $exitCode = $command->execute(['action' => 'list']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $display = $command->getDisplay();
        $this->assertStringContainsString('Task 1', $display);
        $this->assertStringContainsString('Task 2', $display);
        $this->assertStringContainsString('1 hour', $display);
        $this->assertStringContainsString('30 minutes', $display);
    }

    public function testEnableWithoutId(): void
    {
        $command = $this->getCommand();
        $exitCode = $command->execute(['action' => 'enable']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Debes especificar el ID', $command->getDisplay());
    }

    public function testEnableWithInvalidUuid(): void
    {
        $command = $this->getCommand();
        $exitCode = $command->execute([
            'action' => 'enable',
            '--id' => 'invalid-uuid',
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('ID inválido', $command->getDisplay());
    }

    public function testEnableWithNonExistentTask(): void
    {
        $scheduledTaskRepository = $this->createMock(ScheduledTaskRepository::class);
        $scheduledTaskRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willThrowException(new ScheduledTaskNotFoundException());

        $command = $this->getCommand(
            $scheduledTaskRepository,
        );

        $exitCode = $command->execute([
            'action' => 'enable',
            '--id' => UuidMother::create()->value(),
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Tarea no encontrada', $command->getDisplay());
    }

    /**
     * @throws InvalidUuidException
     */
    public function testEnableTask(): void
    {
        $taskId = UuidMother::create();
        $task = ScheduledTaskMother::create(
            id: $taskId,
            name: 'Test Task',
            enabled: false,
        );

        $scheduledTaskRepository = $this->createMock(ScheduledTaskRepository::class);
        $scheduledTaskRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn($task);

        $scheduledTaskRepository
            ->expects($this->once())
            ->method('put')
            ->with($task);

        $command = $this->getCommand(
            $scheduledTaskRepository,
        );
        $exitCode = $command->execute([
            'action' => 'enable',
            '--id' => $taskId->value(),
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('activada correctamente', $command->getDisplay());
        $this->assertTrue($task->isEnabled());
    }

    public function testDisableWithoutId(): void
    {
        $command = $this->getCommand();
        $exitCode = $command->execute(['action' => 'disable']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Debes especificar el ID', $command->getDisplay());
    }

    /**
     * @throws InvalidUuidException
     */
    public function testDisableTask(): void
    {
        $taskId = UuidMother::create();
        $task = ScheduledTaskMother::create(
            id: $taskId,
            name: 'Test Task',
            enabled: true,
        );

        $scheduledTaskRepository = $this->createMock(ScheduledTaskRepository::class);
        $scheduledTaskRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn($task);

        $scheduledTaskRepository
            ->expects($this->once())
            ->method('put')
            ->with($task);

        $command = $this->getCommand(
            $scheduledTaskRepository
        );

        $exitCode = $command->execute([
            'action' => 'disable',
            '--id' => $taskId->value(),
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('desactivada correctamente', $command->getDisplay());
        $this->assertFalse($task->isEnabled());
    }

    public function testCreateWithMissingOptions(): void
    {
        $command = $this->getCommand();
        $exitCode = $command->execute([
            'action' => 'create',
            '--name' => 'New Task',
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Debes especificar --name, --frequency y --message-class', $command->getDisplay());
    }

    public function testCreateWithNonExistentClass(): void
    {
        $command = $this->getCommand();
        $exitCode = $command->execute([
            'action' => 'create',
            '--name' => 'New Task',
            '--frequency' => '1 hour',
            '--message-class' => 'App\\NonExistent\\Class',
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('no existe', $command->getDisplay());
    }

    public function testCreateTask(): void
    {
        $generatedUuid = UuidMother::create();

        $uuidGenerator = $this->createMock(UuidGenerator::class);
        $uuidGenerator
            ->expects($this->once())
            ->method('generate')
            ->willReturn($generatedUuid);

        $scheduledTaskRepository = $this->createMock(ScheduledTaskRepository::class);
        $scheduledTaskRepository
            ->expects($this->once())
            ->method('put')
            ->with(
                $this->callback(function ($task) use ($generatedUuid) {
                    return $task->getId()->equals($generatedUuid)
                        && 'New Task' === $task->getName()
                        && '1 hour' === $task->getFrequency()
                        && \stdClass::class === $task->getMessageClass()
                        && $task->isEnabled();
                })
            );

        $command = $this->getCommand(
            $scheduledTaskRepository,
            $uuidGenerator,
        );
        $exitCode = $command->execute([
            'action' => 'create',
            '--name' => 'New Task',
            '--frequency' => '1 hour',
            '--message-class' => \stdClass::class,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $display = $command->getDisplay();
        $this->assertStringContainsString('Tarea creada correctamente', $display);
        $this->assertStringContainsString('New Task', $display);
        $this->assertStringContainsString('1 hour', $display);
    }

    public function testInvalidAction(): void
    {
        $command = $this->getCommand();
        $exitCode = $command->execute(['action' => 'invalid']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('no válida', $command->getDisplay());
        $this->assertStringContainsString('list, enable, disable, create', $command->getDisplay());
    }
}
