<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Command;

use App\Domain\Scheduler\ScheduledTaskCollection;
use App\Domain\Scheduler\ScheduledTaskCriteria;
use App\Domain\Scheduler\ScheduledTaskRepository;
use App\Domain\Shared\Collection\CollectionException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Infrastructure\Command\SchedulerRunCommand;
use App\Tests\Mother\Domain\Scheduler\ScheduledTaskMother;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Cache\CacheInterface;

class SchedulerRunCommandTest extends TestCase
{
    protected function getCommand(
        ?ScheduledTaskRepository $scheduledTaskRepository = null,
        ?CacheInterface $schedulerCache = null,
        ?MessageBusInterface $messageBus = null,
        ?LoggerInterface $logger = null,
    ): CommandTester {
        $scheduledTaskRepository = $scheduledTaskRepository ?? $this->createStub(ScheduledTaskRepository::class);
        $schedulerCache = $schedulerCache ?? $this->createStub(CacheInterface::class);
        $messageBus = $messageBus ?? $this->createStub(MessageBusInterface::class);
        $logger = $logger ?? $this->createStub(LoggerInterface::class);

        return new CommandTester(
            new SchedulerRunCommand(
                $scheduledTaskRepository,
                $schedulerCache,
                $messageBus,
                $logger,
            )
        );
    }

    /**
     * @throws CollectionException
     */
    public function testExecuteWithNoTasks(): void
    {
        $scheduledTaskRepository = $this->createMock(ScheduledTaskRepository::class);
        $scheduledTaskRepository
            ->expects($this->once())
            ->method('findBy')
            ->with($this->isInstanceOf(ScheduledTaskCriteria::class))
            ->willReturn(new ScheduledTaskCollection([]));

        $command = $this->getCommand($scheduledTaskRepository);
        $exitCode = $command->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('No hay tareas programadas activas', $command->getDisplay());
    }

    /**
     * @throws InvalidUuidException
     * @throws CollectionException
     */
    public function testExecuteWithTaskFirstRun(): void
    {
        $task = ScheduledTaskMother::create(
            name: 'Test Task',
            frequency: '1 hour',
            messageClass: \stdClass::class,
            enabled: true,
        );

        $scheduledTaskRepository = $this->createMock(ScheduledTaskRepository::class);
        $scheduledTaskRepository
            ->expects($this->once())
            ->method('findBy')
            ->willReturn(new ScheduledTaskCollection([$task]));

        $schedulerCache = $this->createMock(CacheInterface::class);
        $schedulerCache
            ->method('get')
            ->willReturn(null);

        $schedulerCache
            ->expects($this->once())
            ->method('delete');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $command = $this->getCommand($scheduledTaskRepository, $schedulerCache, $messageBus);
        $exitCode = $command->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Despachado', $command->getDisplay());
        $this->assertStringContainsString('Test Task', $command->getDisplay());
    }

    /**
     * @throws InvalidUuidException
     * @throws CollectionException
     */
    public function testExecuteWithDryRun(): void
    {
        $task = ScheduledTaskMother::create(
            name: 'Dry Run Task',
            frequency: '1 hour',
            messageClass: \stdClass::class,
            enabled: true,
        );

        $scheduledTaskRepository = $this->createMock(ScheduledTaskRepository::class);
        $scheduledTaskRepository
            ->expects($this->once())
            ->method('findBy')
            ->willReturn(new ScheduledTaskCollection([$task]));

        $schedulerCache = $this->createStub(CacheInterface::class);
        $schedulerCache
            ->method('get')
            ->willReturn(null);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects($this->never())
            ->method('dispatch');

        $command = $this->getCommand($scheduledTaskRepository, $schedulerCache, $messageBus);
        $exitCode = $command->execute(['--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('[DRY-RUN]', $command->getDisplay());
        $this->assertStringContainsString('Dry Run Task', $command->getDisplay());
    }

    /**
     * @throws InvalidUuidException
     * @throws CollectionException
     */
    public function testExecuteWithNonExistentMessageClass(): void
    {
        $task = ScheduledTaskMother::create(
            name: 'Invalid Task',
            frequency: '1 hour',
            messageClass: 'App\\NonExistent\\Class',
            enabled: true,
        );

        $scheduledTaskRepository = $this->createMock(ScheduledTaskRepository::class);
        $scheduledTaskRepository
            ->expects($this->once())
            ->method('findBy')
            ->willReturn(new ScheduledTaskCollection([$task]));

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
            ->with('Scheduled task message class does not exist', $this->anything());

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects($this->never())
            ->method('dispatch');

        $command = $this->getCommand(
            scheduledTaskRepository: $scheduledTaskRepository,
            messageBus: $messageBus,
            logger: $logger
        );
        $exitCode = $command->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Clase no encontrada', $command->getDisplay());
    }

    /**
     * @throws InvalidUuidException
     * @throws \DateMalformedStringException
     * @throws CollectionException
     */
    public function testExecuteWithForceOption(): void
    {
        $task = ScheduledTaskMother::create(
            name: 'Force Task',
            frequency: '1 hour',
            messageClass: \stdClass::class,
            enabled: true,
        );

        $scheduledTaskRepository = $this->createMock(ScheduledTaskRepository::class);
        $scheduledTaskRepository
            ->expects($this->once())
            ->method('findBy')
            ->willReturn(new ScheduledTaskCollection([$task]));

        // Simular que la tarea se ejecutó hace poco (menos de 1 hora)
        $lastRun = (new \DateTimeImmutable())->modify('-30 minutes')->format('c');

        $schedulerCache = $this->createMock(CacheInterface::class);
        $schedulerCache
            ->method('get')
            ->willReturn($lastRun);

        $schedulerCache
            ->expects($this->once())
            ->method('delete');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $command = $this->getCommand($scheduledTaskRepository, $schedulerCache, $messageBus);
        $exitCode = $command->execute(['--force' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Despachado', $command->getDisplay());
    }

    /**
     * @throws InvalidUuidException
     * @throws \DateMalformedStringException
     * @throws CollectionException
     */
    public function testExecuteSkipsTaskNotDueYet(): void
    {
        $task = ScheduledTaskMother::create(
            name: 'Not Due Task',
            frequency: '1 hour',
            messageClass: \stdClass::class,
            enabled: true,
        );

        $scheduledTaskRepository = $this->createMock(ScheduledTaskRepository::class);
        $scheduledTaskRepository
            ->expects($this->once())
            ->method('findBy')
            ->willReturn(new ScheduledTaskCollection([$task]));

        // Simular que la tarea se ejecutó hace poco (menos de 1 hora)
        $lastRun = (new \DateTimeImmutable())->modify('-30 minutes')->format('c');
        $schedulerCache = $this->createStub(CacheInterface::class);
        $schedulerCache
            ->method('get')
            ->willReturn($lastRun);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects($this->never())
            ->method('dispatch');

        $command = $this->getCommand(
            scheduledTaskRepository: $scheduledTaskRepository,
            schedulerCache: $schedulerCache,
            messageBus: $messageBus
        );

        $exitCode = $command->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('No hay tareas pendientes de ejecutar', $command->getDisplay());
    }

    /**
     * @throws InvalidUuidException
     * @throws CollectionException
     */
    public function testExecuteWithCronExpression(): void
    {
        $task = ScheduledTaskMother::create(
            name: 'Cron Task',
            frequency: '*/5 * * * *', // Cada 5 minutos
            messageClass: \stdClass::class,
            enabled: true,
        );

        $scheduledTaskRepository = $this->createMock(ScheduledTaskRepository::class);
        $scheduledTaskRepository
            ->expects($this->once())
            ->method('findBy')
            ->willReturn(new ScheduledTaskCollection([$task]));

        // Primera ejecución (sin lastRun)
        $schedulerCache = $this->createMock(CacheInterface::class);
        $schedulerCache
            ->method('get')
            ->willReturn(null);

        $schedulerCache
            ->expects($this->once())
            ->method('delete');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $command = $this->getCommand(
            scheduledTaskRepository: $scheduledTaskRepository,
            schedulerCache: $schedulerCache,
            messageBus: $messageBus
        );
        $exitCode = $command->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Despachado', $command->getDisplay());
    }

    /**
     * @throws InvalidUuidException
     * @throws CollectionException
     */
    public function testExecuteWithMultipleTasks(): void
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
            enabled: true,
        );

        $scheduledTaskRepository = $this->createMock(ScheduledTaskRepository::class);
        $scheduledTaskRepository
            ->expects($this->once())
            ->method('findBy')
            ->willReturn(new ScheduledTaskCollection([$task1, $task2]));

        $schedulerCache = $this->createStub(CacheInterface::class);
        $schedulerCache
            ->method('get')
            ->willReturn(null);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $command = $this->getCommand(
            scheduledTaskRepository: $scheduledTaskRepository,
            schedulerCache: $schedulerCache,
            messageBus: $messageBus
        );
        $exitCode = $command->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('2 tarea(s) despachada(s)', $command->getDisplay());
    }
}
