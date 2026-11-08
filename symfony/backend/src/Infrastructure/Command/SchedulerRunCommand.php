<?php

declare(strict_types=1);

namespace App\Infrastructure\Command;

use App\Domain\Scheduler\ScheduledTaskCriteria;
use App\Domain\Scheduler\ScheduledTaskRepository;
use App\Domain\Shared\Exception\InfrastructureException;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Trigger\CronExpressionTrigger;
use Symfony\Contracts\Cache\CacheInterface;

#[AsCommand(
    name: 'app:scheduler:run',
    description: 'Ejecuta las tareas programadas pendientes y sale. Ideal para cron.',
)]
class SchedulerRunCommand extends Command
{
    private const string CACHE_KEY_PREFIX = 'scheduler_last_run_';

    public function __construct(
        private readonly ScheduledTaskRepository $scheduledTaskRepository,
        private readonly CacheInterface $schedulerCache,
        private readonly MessageBusInterface $messageBus,
        private readonly ?LoggerInterface $logger = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Muestra las tareas que se ejecutarían sin ejecutarlas')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Fuerza la ejecución de todas las tareas ignorando el estado');
    }

    /**
     * @throws InfrastructureException
     * @throws ExceptionInterface
     * @throws InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');

        $criteria = ScheduledTaskCriteria::createEmpty()
            ->filterByEnabled(true);

        $tasks = $this->scheduledTaskRepository->findBy($criteria);

        if ($tasks->isEmpty()) {
            $io->info('No hay tareas programadas activas.');

            return Command::SUCCESS;
        }

        $now = new \DateTimeImmutable();
        $messagesDispatched = 0;

        foreach ($tasks as $task) {
            $messageClass = $task->getMessageClass();

            if (!class_exists($messageClass)) {
                $this->logger?->warning('Scheduled task message class does not exist', [
                    'task_id' => $task->getId()->value(),
                    'task_name' => $task->getName(),
                    'message_class' => $messageClass,
                ]);
                $io->warning(\sprintf('Clase no encontrada: %s', $messageClass));
                continue;
            }

            $cacheKey = self::CACHE_KEY_PREFIX . $task->getId()->value();
            $lastRun = $this->getLastRun($cacheKey);
            $frequency = $task->getFrequency();

            // Calcular si debe ejecutarse
            $shouldRun = $force || $this->shouldRunTask($lastRun, $frequency, $now);

            if (!$shouldRun) {
                $io->writeln(\sprintf(
                    'Omitida: <comment>%s</comment> (última ejecución: %s)',
                    $task->getName(),
                    $lastRun?->format('Y-m-d H:i:s') ?? 'nunca'
                ), OutputInterface::VERBOSITY_VERBOSE);
                continue;
            }

            if ($dryRun) {
                $io->writeln(\sprintf(
                    '[DRY-RUN] Ejecutaría: <info>%s</info> (%s)',
                    $task->getName(),
                    $messageClass
                ));
            } else {
                $message = new $messageClass();
                $this->messageBus->dispatch($message);
                $this->saveLastRun($cacheKey, $now);

                $io->writeln(\sprintf(
                    'Despachado: <info>%s</info>',
                    $task->getName()
                ));

                $this->logger?->info('Scheduled task dispatched', [
                    'task_id' => $task->getId()->value(),
                    'task_name' => $task->getName(),
                    'message_class' => $messageClass,
                ]);
            }

            ++$messagesDispatched;
        }

        if (0 === $messagesDispatched) {
            $io->info('No hay tareas pendientes de ejecutar.');
        } else {
            $io->success(\sprintf('%d tarea(s) %s.', $messagesDispatched, $dryRun ? 'pendiente(s)' : 'despachada(s)'));
        }

        return Command::SUCCESS;
    }

    private function shouldRunTask(?\DateTimeImmutable $lastRun, string $frequency, \DateTimeImmutable $now): bool
    {
        if (null === $lastRun) {
            return true;
        }

        if ($this->isCronExpression($frequency)) {
            return $this->shouldRunCronTask($lastRun, $frequency, $now);
        }

        $interval = $this->parseFrequencyToSeconds($frequency);
        $elapsed = $now->getTimestamp() - $lastRun->getTimestamp();

        return $elapsed >= $interval;
    }

    private function shouldRunCronTask(\DateTimeImmutable $lastRun, string $cronExpression, \DateTimeImmutable $now): bool
    {
        $cron = CronExpressionTrigger::fromSpec($cronExpression);
        $nextRun = $cron->getNextRunDate($lastRun);

        if (null === $nextRun) {
            return false;
        }

        return $nextRun <= $now;
    }

    /**
     * Detecta si la frecuencia es una expresión cron.
     */
    private function isCronExpression(string $frequency): bool
    {
        $frequency = trim($frequency);
        $parts = preg_split('/\s+/', $frequency);

        if (false === $parts) {
            return false;
        }

        if (5 !== \count($parts)) {
            return false;
        }

        $cronPattern = '/^[\d\*\/\-\,]+$/';

        foreach ($parts as $part) {
            if (!preg_match($cronPattern, $part)) {
                return false;
            }
        }

        return true;
    }

    private function parseFrequencyToSeconds(string $frequency): int
    {
        $frequency = strtolower(trim($frequency));

        // Intentar parsear con DateInterval
        try {
            $interval = \DateInterval::createFromDateString($frequency);
            $reference = new \DateTimeImmutable();
            $endTime = $reference->add($interval);

            return $endTime->getTimestamp() - $reference->getTimestamp();
        } catch (\Exception) {
            // Si falla, intentar parsear manualmente
        }

        // Parseo manual para formatos comunes
        if (preg_match('/^(\d+)\s*(second|seconds|sec|s)$/i', $frequency, $matches)) {
            return (int) $matches[1];
        }
        if (preg_match('/^(\d+)\s*(minute|minutes|min|m)$/i', $frequency, $matches)) {
            return (int) $matches[1] * 60;
        }
        if (preg_match('/^(\d+)\s*(hour|hours|h)$/i', $frequency, $matches)) {
            return (int) $matches[1] * 3600;
        }
        if (preg_match('/^(\d+)\s*(day|days|d)$/i', $frequency, $matches)) {
            return (int) $matches[1] * 86400;
        }
        if (preg_match('/^(\d+)\s*(week|weeks|w)$/i', $frequency, $matches)) {
            return (int) $matches[1] * 604800;
        }

        // Por defecto, 1 hora
        $this->logger?->warning('Could not parse frequency, defaulting to 1 hour', [
            'frequency' => $frequency,
        ]);

        return 3600;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function getLastRun(string $cacheKey): ?\DateTimeImmutable
    {
        /** @var string|null $item */
        $item = $this->schedulerCache->get($cacheKey, fn () => null);

        if (null === $item) {
            return null;
        }

        try {
            return new \DateTimeImmutable($item);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function saveLastRun(string $cacheKey, \DateTimeImmutable $time): void
    {
        $this->schedulerCache->delete($cacheKey);
        $this->schedulerCache->get($cacheKey, fn () => $time->format('c'));
    }
}
