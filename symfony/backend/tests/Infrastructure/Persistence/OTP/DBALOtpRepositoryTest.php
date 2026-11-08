<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Persistence\OTP;

use App\Domain\OTP\OtpCriteria;
use App\Domain\OTP\OtpRepository;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Infrastructure\Persistence\DBALDateTimeFormatter;
use App\Infrastructure\Persistence\OTP\DBALOtpRepository;
use App\Tests\Domain\OTP\OtpRepositoryTestCase;
use App\Tests\Infrastructure\Persistence\DBAL\DBALConnectionFactory;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(DBALOtpRepository::class)]
class DBALOtpRepositoryTest extends OtpRepositoryTestCase
{
    private const string TABLE_NAME = '"otp"';
    private Connection $connection;

    /**
     * @throws Exception
     */
    protected function getRepository(): OtpRepository
    {
        [$this->connection] = DBALConnectionFactory::createNewConnection();

        $this->createTable();

        return new DBALOtpRepository(
            connection: $this->connection,
            otpTableName: self::TABLE_NAME,
        );
    }

    /**
     * @throws Exception
     */
    private function createTable(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS ' . self::TABLE_NAME . ' CASCADE');

        $schema = new Schema();

        // Create user table with JSONB roles field
        $table = $schema->createTable(self::TABLE_NAME);
        $table->addColumn('id', 'string', ['length' => 36]);
        $table->addColumn('resource_id', 'string', ['length' => 36]);
        $table->addColumn('resource_type', 'string', ['length' => 50]);
        $table->addColumn('purpose', 'string', ['length' => 50]);
        $table->addColumn('code_secret', 'string', ['length' => 64]);
        $table->addColumn('attempts', 'integer');
        $table->addColumn('max_attempts', 'integer');
        $table->addColumn('revoked', 'boolean');
        $table->addColumn('expires_at', 'datetime', ['columnDefinition' => 'TIMESTAMP(6)']);
        $table->addColumn('consumed_at', 'datetime', ['columnDefinition' => 'TIMESTAMP(6)', 'notnull' => false]);
        $table->addColumn('created_at', 'datetime', ['columnDefinition' => 'TIMESTAMP(6)']);
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
        $this->insertRawOtp($invalidValues);

        $this->expectException(InfrastructureException::class);

        $repository->findOneBy(OtpCriteria::createEmpty());
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
        $this->insertRawOtp($invalidValues);

        $this->expectException(InfrastructureException::class);

        $repository->findBy(OtpCriteria::createEmpty());
    }

    /**
     * @return \Generator<string, array{array<string, string>}>
     */
    public static function invalidPersistedRowProvider(): \Generator
    {
        yield 'invalid uuid in id' => [['id' => 'not-a-uuid']];
        yield 'invalid uuid in resource_id' => [['resource_id' => 'not-a-uuid']];
    }

    /**
     * Inserta saltándose el repositorio: es la única forma de simular una fila que ya está corrupta.
     *
     * @param array<string, string> $invalidValues
     *
     * @throws Exception
     * @throws InvalidUuidException
     */
    private function insertRawOtp(array $invalidValues): void
    {
        $this->connection->insert(
            self::TABLE_NAME,
            [
                'id' => UuidMother::create()->value(),
                'resource_id' => UuidMother::create()->value(),
                'resource_type' => 'USER',
                'purpose' => 'LOGIN_2FA',
                'code_secret' => 'secret',
                'attempts' => 0,
                'max_attempts' => 3,
                'revoked' => 0,
                'expires_at' => DBALDateTimeFormatter::format(new \DateTimeImmutable('+10 minutes')),
                'consumed_at' => null,
                'created_at' => DBALDateTimeFormatter::format(new \DateTimeImmutable()),
                ...$invalidValues,
            ]
        );
    }
}
