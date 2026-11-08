<?php

declare(strict_types=1);

namespace App\Tests\Functional\Database;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Depends;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class MigrationsTest extends WebTestCase
{
    private Application $application;

    /**
     * @throws \Exception
     */
    protected function setUp(): void
    {
        $client = self::createClient();

        $this->application = new Application($client->getKernel());
        $this->ensureDatabaseExists();
        $this->dropTables();
    }

    /**
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        $this->dropTables();
        parent::tearDown();
    }

    /**
     * Ensure the test database exists before running tests.
     *
     * @throws \Exception
     */
    private function ensureDatabaseExists(): void
    {
        $container = $this->application->getKernel()->getContainer();
        /** @var Connection $connection */
        $connection = $container->get('doctrine.dbal.default_connection');
        $params = $connection->getParams();

        // Get database name from connection params
        $dbName = $params['dbname'] ?? null;

        if (!$dbName) {
            return; // Skip if no database name is configured
        }

        // Create a temporary connection without database selection
        $tmpParams = $params;
        $tmpParams['dbname'] = 'postgres';

        $tmpConnection = \Doctrine\DBAL\DriverManager::getConnection($tmpParams);

        // Check if database exists
        $result = $tmpConnection->executeQuery(
            'SELECT 1 FROM pg_database WHERE datname = :dbname',
            ['dbname' => $dbName]
        );

        if (!$result->fetchOne()) {
            // Create database if it doesn't exist
            $tmpConnection->executeStatement(\sprintf(
                'CREATE DATABASE "%s"',
                $dbName
            ));
        }

        $tmpConnection->close();
    }

    /**
     * @throws \Exception
     */
    private function dropTables(): void
    {
        $container = $this->application->getKernel()->getContainer();
        /** @var Connection $connection */
        $connection = $container->get('doctrine.dbal.default_connection');

        $schemaManager = $connection->createSchemaManager();
        $tables = $schemaManager->listTableNames();

        foreach ($tables as $tableName) {
            // Skip empty table names to avoid SQL syntax errors
            if (empty($tableName)) {
                continue;
            }
            $connection->executeStatement(\sprintf('DROP TABLE IF EXISTS %s CASCADE', $tableName));
        }
    }

    public function testUp(): void
    {
        $this->expectNotToPerformAssertions();
        $command = $this->application->find('doctrine:migrations:migrate');
        $commandTester = new CommandTester($command);
        $commandTester->execute(
            [
                '--no-interaction' => true,
            ]
        );
    }

    #[Depends('testUp')]
    public function testDown(): void
    {
        $this->expectNotToPerformAssertions();
        $command = $this->application->find('doctrine:migrations:migrate');
        $commandTester = new CommandTester($command);
        $commandTester->execute(
            [
                '--no-interaction' => true,
                'version' => 'first',
            ]
        );
    }
}
