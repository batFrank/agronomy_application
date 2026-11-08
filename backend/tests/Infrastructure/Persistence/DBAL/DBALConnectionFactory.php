<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Persistence\DBAL;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Schema;

class DBALConnectionFactory
{
    /**
     * @return array{Connection, Schema}
     *
     * @throws Exception
     */
    public static function createNewConnection(): array
    {
        $config = new Configuration();

        if ('true' === $_ENV['USE_SQLITE'] || !isset($_ENV['DB_HOST'])) {
            $params = [
                'user' => 'root',
                'password' => 'root',
                'driver' => 'pdo_sqlite',
                'path' => ':memory:',
            ];
        } else {
            // Get TEST_TOKEN from environment (set by ParaTest for parallel execution)
            $testToken = $_ENV['TEST_TOKEN'] ?? $_SERVER['TEST_TOKEN'] ?? '';
            $dbName = $_ENV['DB_NAME'] . ('' !== $testToken ? '_' . $testToken : '');

            $params = [
                'driver' => $_ENV['DB_DRIVER'],
                'host' => $_ENV['DB_HOST'],
                'port' => (int) $_ENV['DB_PORT'],
                'server_version' => $_ENV['DB_VERSION'],
                'dbname' => $dbName,
                'user' => $_ENV['DB_USERNAME'],
                'password' => $_ENV['DB_PASSWORD'],
            ];
        }

        // Create database if it doesn't exist (only for MySQL/MariaDB)
        if (!isset($params['path'])) {
            self::createDatabaseIfNotExists($params);
        }

        $connection = DriverManager::getConnection($params, $config);
        $schemaManager = $connection->createSchemaManager();
        $schema = $schemaManager->introspectSchema();

        return [$connection, $schema];
    }

    /**
     * Create database if it doesn't exist.
     *
     * @param array<string, mixed> $params
     *
     * @throws Exception
     */
    private static function createDatabaseIfNotExists(array $params): void
    {
        $tmpConnection = DriverManager::getConnection([
            'driver' => $params['driver'],
            'host' => $params['host'],
            'port' => $params['port'],
            'user' => $params['user'],
            'password' => $params['password'],
        ]);

        // Check if database exists
        $result = $tmpConnection->executeQuery(
            'SELECT 1 FROM pg_database WHERE datname = :dbname',
            ['dbname' => $params['dbname']]
        );

        if (!$result->fetchOne()) {
            // Create database if it doesn't exist
            $tmpConnection->executeStatement(\sprintf(
                'CREATE DATABASE "%s"',
                $params['dbname']
            ));
        }

        $tmpConnection->close();
    }
}
