<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Persistence\Security;

use App\Domain\Security\RefreshTokenRepository;
use App\Infrastructure\Persistence\Security\DBALRefreshTokenRepository;
use App\Tests\Domain\Security\RefreshTokenRepositoryTestCase;
use App\Tests\Infrastructure\Persistence\DBAL\DBALConnectionFactory;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;

class DBALRefreshTokenRepositoryTest extends RefreshTokenRepositoryTestCase
{
    private const string TABLE_NAME = 'refresh_token';
    private Connection $connection;

    /**
     * @throws Exception
     */
    protected function getRepository(): RefreshTokenRepository
    {
        [$this->connection] = DBALConnectionFactory::createNewConnection();

        $this->createTable();

        return new DBALRefreshTokenRepository(
            $this->connection,
            self::TABLE_NAME,
        );
    }

    /**
     * @throws Exception
     */
    private function createTable(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS ' . self::TABLE_NAME . ' CASCADE');

        $schema = new Schema();

        // Create refresh_tokens table
        $table = $schema->createTable(self::TABLE_NAME);
        $table->addColumn('refresh_token', 'string', ['length' => 128]);
        $table->addColumn('username', 'string', ['length' => 255]);
        $table->addColumn('valid', 'datetime', ['columnDefinition' => 'TIMESTAMP(6)']);
        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('refresh_token')
                ->create()
        );

        $sql = $schema->toSql($this->connection->getDatabasePlatform());
        foreach ($sql as $query) {
            $this->connection->executeStatement($query);
        }
    }

    /**
     * @throws Exception
     */
    protected function tearDown(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS ' . self::TABLE_NAME . ' CASCADE');

        parent::tearDown();
    }
}
