<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Persistence\ApiKey;

use App\Domain\ApiKey\ApiKeyRepository;
use App\Domain\ApiKey\Exception\ApiKeyRepositoryException;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Infrastructure\Persistence\ApiKey\DBALApiKeyRepository;
use App\Tests\Domain\ApiKey\ApiKeyRepositoryTestCase;
use App\Tests\Infrastructure\Persistence\DBAL\DBALConnectionFactory;
use App\Tests\Mother\Domain\ApiKey\ApiKeyMother;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;

class DBALApiKeyRepositoryTest extends ApiKeyRepositoryTestCase
{
    private const string API_KEY_TABLE_NAME = 'api_key';

    private Connection $connection;

    /**
     * @throws Exception
     */
    protected function getRepository(): ApiKeyRepository
    {
        [$this->connection] = DBALConnectionFactory::createNewConnection();

        $this->createTables();

        return new DBALApiKeyRepository(
            connection: $this->connection,
            apiKeyTableName: self::API_KEY_TABLE_NAME,
        );
    }

    /**
     * @throws Exception
     * @throws InfrastructureException
     * @throws InvalidUuidException
     */
    public function testPutThrowsSemanticExceptionWhenApiKeyIsDuplicated(): void
    {
        $this->expectException(ApiKeyRepositoryException::class);
        $this->expectExceptionMessage('Api key already exists');

        $repository = $this->getRepository();

        $repository->put(ApiKeyMother::create(apiKey: 'duplicated-key'));
        $repository->put(ApiKeyMother::create(apiKey: 'duplicated-key'));
    }

    /**
     * @throws Exception
     */
    private function createTables(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS ' . self::API_KEY_TABLE_NAME . ' CASCADE');

        $schema = new Schema();

        $apiKeyTable = $schema->createTable(self::API_KEY_TABLE_NAME);
        $apiKeyTable->addColumn('id', 'string', ['length' => 36]);
        $apiKeyTable->addColumn('api_key', 'string', ['length' => 255]);
        $apiKeyTable->addColumn('active', 'boolean');
        $apiKeyTable->addColumn('user_id', 'string', ['length' => 36, 'notnull' => false]);
        $apiKeyTable->addColumn('created_at', 'datetime', ['columnDefinition' => 'TIMESTAMP(6)']);
        $apiKeyTable->addColumn('updated_at', 'datetime', ['columnDefinition' => 'TIMESTAMP(6)', 'notnull' => false]);
        $apiKeyTable->addColumn('deleted_at', 'datetime', ['columnDefinition' => 'TIMESTAMP(6)', 'notnull' => false]);
        $apiKeyTable->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id')
                ->create(),
        );
        $apiKeyTable->addUniqueIndex(['api_key']);
        $apiKeyTable->addIndex(['deleted_at']);
        $apiKeyTable->addIndex(['user_id']);

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
        $this->connection->executeStatement('DROP TABLE IF EXISTS ' . self::API_KEY_TABLE_NAME . ' CASCADE');

        parent::tearDown();
    }
}
