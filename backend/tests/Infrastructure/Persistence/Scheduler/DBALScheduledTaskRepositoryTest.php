<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Persistence\Scheduler;

use App\Domain\Scheduler\ScheduledTaskRepository;
use App\Infrastructure\Persistence\Scheduler\DBALScheduledTaskRepository;
use App\Tests\Domain\Scheduler\ScheduledTaskRepositoryTestCase;
use App\Tests\Infrastructure\Persistence\DBAL\DBALConnectionFactory;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;

class DBALScheduledTaskRepositoryTest extends ScheduledTaskRepositoryTestCase
{
    private const string TABLE_NAME = 'scheduled_task';
    private Connection $connection;

    /**
     * @throws Exception
     */
    protected function getRepository(): ScheduledTaskRepository
    {
        [$this->connection] = DBALConnectionFactory::createNewConnection();

        $this->createTable();

        return new DBALScheduledTaskRepository(
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

        $table = $schema->createTable(self::TABLE_NAME);
        $table->addColumn('id', 'string', ['length' => 36]);
        $table->addColumn('name', 'string', ['length' => 255]);
        $table->addColumn('frequency', 'string', ['length' => 255]);
        $table->addColumn('message_class', 'string', ['length' => 255]);
        $table->addColumn('enabled', 'boolean');
        $table->addColumn('created_at', 'datetime', ['columnDefinition' => 'TIMESTAMP(6)']);
        $table->addColumn('updated_at', 'datetime', ['columnDefinition' => 'TIMESTAMP(6)', 'notnull' => false]);
        $table->addColumn('deleted_at', 'datetime', ['columnDefinition' => 'TIMESTAMP(6)', 'notnull' => false]);
        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id')
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
