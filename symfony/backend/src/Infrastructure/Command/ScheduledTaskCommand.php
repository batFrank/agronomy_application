<?php

declare(strict_types=1);

namespace App\Infrastructure\Command;

use App\Domain\Scheduler\Exception\ScheduledTaskNotFoundException;
use App\Domain\Scheduler\ScheduledTask;
use App\Domain\Scheduler\ScheduledTaskCriteria;
use App\Domain\Scheduler\ScheduledTaskRepository;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Shared\Uuid\Uuid;
use App\Domain\Shared\Uuid\UuidGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:scheduled-task',
    description: 'Gestiona las tareas programadas (listar, activar, desactivar, crear)',
)]
class ScheduledTaskCommand extends Command
{
    public function __construct(
        private readonly ScheduledTaskRepository $scheduledTaskRepository,
        private readonly UuidGenerator $uuidGenerator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::REQUIRED, 'Acción a realizar: list, enable, disable, create')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'ID de la tarea (para enable/disable)')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Nombre de la tarea (para create)')
            ->addOption('frequency', null, InputOption::VALUE_REQUIRED, 'Frecuencia de la tarea (para create), ej: "1 hour", "30 minutes"')
            ->addOption('message-class', null, InputOption::VALUE_REQUIRED, 'Clase del mensaje (para create), ej: App\\Scheduler\\Message\\MiMessage');
    }

    /**
     * @throws InfrastructureException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');

        return match ($action) {
            'list' => $this->listTasks($io),
            'enable' => $this->enableTask($io, $input->getOption('id')),
            'disable' => $this->disableTask($io, $input->getOption('id')),
            'create' => $this->createTask($io, $input),
            default => $this->invalidAction($io, $action),
        };
    }

    /**
     * @throws InfrastructureException
     */
    private function listTasks(SymfonyStyle $io): int
    {
        $tasks = $this->scheduledTaskRepository->findBy(ScheduledTaskCriteria::createEmpty());

        if ($tasks->isEmpty()) {
            $io->info('No hay tareas programadas.');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($tasks as $task) {
            $rows[] = [
                $task->getId()->value(),
                $task->getName(),
                $task->getFrequency(),
                $task->getMessageClass(),
                $task->isEnabled() ? '<info>Sí</info>' : '<comment>No</comment>',
            ];
        }

        $io->table(
            ['ID', 'Nombre', 'Frecuencia', 'Clase del Mensaje', 'Activa'],
            $rows
        );

        return Command::SUCCESS;
    }

    /**
     * @throws InfrastructureException
     */
    private function enableTask(SymfonyStyle $io, ?string $id): int
    {
        if (null === $id) {
            $io->error('Debes especificar el ID de la tarea con --id');

            return Command::FAILURE;
        }

        try {
            $task = $this->scheduledTaskRepository->findOneBy(
                ScheduledTaskCriteria::createById(new Uuid($id))
            );

            $task->enable();
            $this->scheduledTaskRepository->put($task);

            $io->success(\sprintf('Tarea "%s" activada correctamente.', $task->getName()));

            return Command::SUCCESS;
        } catch (InvalidUuidException) {
            $io->error('ID inválido.');

            return Command::FAILURE;
        } catch (ScheduledTaskNotFoundException) {
            $io->error('Tarea no encontrada.');

            return Command::FAILURE;
        }
    }

    /**
     * @throws InfrastructureException
     */
    private function disableTask(SymfonyStyle $io, ?string $id): int
    {
        if (null === $id) {
            $io->error('Debes especificar el ID de la tarea con --id');

            return Command::FAILURE;
        }

        try {
            $task = $this->scheduledTaskRepository->findOneBy(
                ScheduledTaskCriteria::createById(new Uuid($id))
            );

            $task->disable();
            $this->scheduledTaskRepository->put($task);

            $io->success(\sprintf('Tarea "%s" desactivada correctamente.', $task->getName()));

            return Command::SUCCESS;
        } catch (InvalidUuidException) {
            $io->error('ID inválido.');

            return Command::FAILURE;
        } catch (ScheduledTaskNotFoundException) {
            $io->error('Tarea no encontrada.');

            return Command::FAILURE;
        }
    }

    private function createTask(SymfonyStyle $io, InputInterface $input): int
    {
        $name = $input->getOption('name');
        $frequency = $input->getOption('frequency');
        $messageClass = $input->getOption('message-class');

        if (null === $name || null === $frequency || null === $messageClass) {
            $io->error('Debes especificar --name, --frequency y --message-class');

            return Command::FAILURE;
        }

        if (!class_exists($messageClass)) {
            $io->error(\sprintf('La clase "%s" no existe.', $messageClass));

            return Command::FAILURE;
        }

        $task = new ScheduledTask(
            id: $this->uuidGenerator->generate(),
            name: $name,
            frequency: $frequency,
            messageClass: $messageClass,
            enabled: true,
            createdAt: new \DateTimeImmutable(),
        );

        $this->scheduledTaskRepository->put($task);

        $io->success([
            'Tarea creada correctamente!',
            \sprintf('ID: %s', $task->getId()->value()),
            \sprintf('Nombre: %s', $task->getName()),
            \sprintf('Frecuencia: %s', $task->getFrequency()),
            \sprintf('Clase: %s', $task->getMessageClass()),
        ]);

        return Command::SUCCESS;
    }

    private function invalidAction(SymfonyStyle $io, string $action): int
    {
        $io->error(\sprintf('Acción "%s" no válida. Usa: list, enable, disable, create', $action));

        return Command::FAILURE;
    }
}
