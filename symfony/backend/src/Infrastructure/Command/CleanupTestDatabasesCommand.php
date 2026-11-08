<?php

declare(strict_types=1);

namespace App\Infrastructure\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-test-databases',
    description: 'Cleanup test databases created by ParaTest parallel execution',
)]
class CleanupTestDatabasesCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Show which databases would be deleted without actually deleting them'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force deletion without confirmation'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');

        try {
            // Get the base database name from environment
            // ParaTest creates databases with suffix _1, _2, etc.
            $baseDatabaseName = $_ENV['DB_NAME'] ?? 'symfony_base_test';
            $testDatabasePattern = $baseDatabaseName . '_%';

            $io->title('Cleanup Test Databases');
            $io->text(\sprintf('Looking for databases matching pattern: <info>%s</info>', $testDatabasePattern));

            // Get list of all databases
            $databases = $this->getTestDatabases($testDatabasePattern);

            if (empty($databases)) {
                $io->success('No test databases found to cleanup.');

                return Command::SUCCESS;
            }

            $io->section(\sprintf('Found %d test database(s):', \count($databases)));
            $io->listing($databases);

            if ($dryRun) {
                $io->note('DRY RUN: No databases were deleted.');

                return Command::SUCCESS;
            }

            // Ask for confirmation unless --force is used
            if (!$force) {
                if (!$io->confirm(
                    \sprintf('Are you sure you want to delete %d database(s)?', \count($databases)),
                    false
                )) {
                    $io->warning('Operation cancelled.');

                    return Command::SUCCESS;
                }
            }

            // Delete databases
            $deleted = 0;
            $failed = 0;

            foreach ($databases as $database) {
                try {
                    $this->connection->executeStatement(\sprintf('DROP DATABASE IF EXISTS "%s"', $database));
                    $io->writeln(\sprintf('  <info>✓</info> Deleted: %s', $database));
                    ++$deleted;
                } catch (DBALException $e) {
                    $io->writeln(\sprintf('  <error>✗</error> Failed to delete: %s (%s)', $database, $e->getMessage()));
                    ++$failed;
                }
            }

            $io->newLine();
            if (0 === $failed) {
                $io->success(\sprintf('Successfully deleted %d test database(s).', $deleted));
            } else {
                $io->warning(\sprintf('Deleted %d database(s), failed to delete %d.', $deleted, $failed));
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(\sprintf('An error occurred: %s', $e->getMessage()));

            return Command::FAILURE;
        }
    }

    /**
     * Get list of test databases matching the pattern.
     *
     * @return array<string>
     *
     * @throws DBALException
     */
    private function getTestDatabases(string $pattern): array
    {
        $result = $this->connection->executeQuery(
            'SELECT datname FROM pg_database WHERE datname LIKE :pattern',
            ['pattern' => $pattern]
        );
        $databases = [];

        while ($row = $result->fetchNumeric()) {
            $databases[] = $row[0];
        }

        return $databases;
    }
}
