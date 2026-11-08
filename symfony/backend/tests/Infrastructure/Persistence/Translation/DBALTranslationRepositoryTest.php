<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Persistence\Translation;

use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Translation\TranslationCriteria;
use App\Domain\Translation\TranslationRepository;
use App\Infrastructure\Persistence\DBALDateTimeFormatter;
use App\Infrastructure\Persistence\Translation\DBALTranslationRepository;
use App\Tests\Domain\Translation\TranslationRepositoryTestCase;
use App\Tests\Infrastructure\Persistence\DBAL\DBALConnectionFactory;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

class DBALTranslationRepositoryTest extends TranslationRepositoryTestCase
{
    private const string TABLE_NAME = 'translation';
    private Connection $connection;

    /**
     * @throws Exception
     */
    protected function getRepository(): TranslationRepository
    {
        [$this->connection] = DBALConnectionFactory::createNewConnection();

        $this->createTable();

        return new DBALTranslationRepository(
            $this->connection,
            self::TABLE_NAME
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
        $table->addColumn('entity_type', 'string', ['length' => 255]);
        $table->addColumn('entity_id', 'string', ['length' => 255]);
        $table->addColumn('field', 'string', ['length' => 255]);
        $table->addColumn('locale', 'string', ['length' => 10]);
        $table->addColumn('translation', 'text');
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
     * Una fila corrupta en base de datos no es culpa de quien hace la petición: las excepciones de
     * value object que surjan al hidratarla se envuelven en InfrastructureException y siguen
     * clasificando como 500, no como el 422 que les correspondería viniendo del cliente.
     *
     * @param array<string, string> $invalidValues
     *
     * @throws Exception
     * @throws InvalidUuidException
     */
    #[DataProvider('invalidPersistedRowProvider')]
    public function testFindOneByWrapsInvalidPersistedValuesAsInfrastructureError(array $invalidValues): void
    {
        $repository = $this->getRepository();
        $this->insertRawTranslation($invalidValues);

        $this->expectException(InfrastructureException::class);

        $repository->findOneBy(TranslationCriteria::createEmpty());
    }

    /**
     * @param array<string, string> $invalidValues
     *
     * @throws Exception
     * @throws InvalidUuidException
     */
    #[DataProvider('invalidPersistedRowProvider')]
    public function testFindByWrapsInvalidPersistedValuesAsInfrastructureError(array $invalidValues): void
    {
        $repository = $this->getRepository();
        $this->insertRawTranslation($invalidValues);

        $this->expectException(InfrastructureException::class);

        $repository->findBy(TranslationCriteria::createEmpty());
    }

    /**
     * @return \Generator<string, array{array<string, string>}>
     */
    public static function invalidPersistedRowProvider(): \Generator
    {
        yield 'invalid uuid in id' => [['id' => 'not-a-uuid']];
        yield 'invalid uuid in entity_id' => [['entity_id' => 'not-a-uuid']];
        yield 'unsupported locale' => [['locale' => 'xx']];
    }

    /**
     * Inserta saltándose el repositorio: es la única forma de simular una fila que ya está corrupta.
     *
     * @param array<string, string> $invalidValues
     *
     * @throws Exception
     * @throws InvalidUuidException
     */
    private function insertRawTranslation(array $invalidValues): void
    {
        $this->connection->insert(
            self::TABLE_NAME,
            [
                'id' => UuidMother::create()->value(),
                'entity_type' => 'entity',
                'entity_id' => UuidMother::create()->value(),
                'field' => 'name',
                'locale' => 'es',
                'translation' => 'traducción',
                'created_at' => DBALDateTimeFormatter::format(new \DateTimeImmutable()),
                'updated_at' => null,
                'deleted_at' => null,
                ...$invalidValues,
            ]
        );
    }

    /**
     * @throws Exception
     */
    protected function tearDown(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS ' . self::TABLE_NAME);

        parent::tearDown();
    }
}
