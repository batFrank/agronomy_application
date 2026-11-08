<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Persistence\User;

use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\User\Exception\EmailAlreadyInUseException;
use App\Domain\User\UserRepository;
use App\Infrastructure\Persistence\User\DBALUserRepository;
use App\Tests\Domain\User\UserRepositoryTestCase;
use App\Tests\Infrastructure\Persistence\DBAL\DBALConnectionFactory;
use App\Tests\Mother\Domain\Shared\Email\EmailMother;
use App\Tests\Mother\Domain\User\UserMother;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;

class DBALUserRepositoryTest extends UserRepositoryTestCase
{
    private const string TABLE_NAME = '"user"';
    private Connection $connection;

    /**
     * @throws Exception
     */
    protected function getRepository(): UserRepository
    {
        [$this->connection] = DBALConnectionFactory::createNewConnection();

        $this->createTable();

        return new DBALUserRepository(
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

        // Create user table with JSONB roles field
        $table = $schema->createTable(self::TABLE_NAME);
        $table->addColumn('id', 'string', ['length' => 36]);
        $table->addColumn('email', 'string', ['length' => 255]);
        $table->addColumn('roles', 'json', ['columnDefinition' => 'JSONB']);
        $table->addColumn('password_hash', 'text');
        $table->addColumn('created_at', 'datetime', ['columnDefinition' => 'TIMESTAMP(6)']);
        $table->addColumn('updated_at', 'datetime', ['columnDefinition' => 'TIMESTAMP(6)', 'notnull' => false]);
        $table->addColumn('deleted_at', 'datetime', ['columnDefinition' => 'TIMESTAMP(6)', 'notnull' => false]);
        $table->addColumn('password_reset_token', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('password_reset_token_expiration', 'datetime', ['columnDefinition' => 'TIMESTAMP(6)', 'notnull' => false]);
        $table->addColumn('created_by', 'string', ['length' => 36, 'notnull' => false]);
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

    /**
     * Mirrors the real UNIQ_user_email index from the migrations, which the hand-built schema above
     * does not declare. Added only for the two tests below, so the rest of UserRepositoryTestCase
     * (which creates several users sharing the Mother's default email) is unaffected.
     *
     * @throws Exception
     */
    private function addEmailUniqueIndex(): void
    {
        $this->connection->executeStatement('CREATE UNIQUE INDEX UNIQ_user_email ON ' . self::TABLE_NAME . ' (email)');
    }

    /**
     * @throws Exception
     * @throws EmailAlreadyInUseException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     */
    public function testPutThrowsEmailAlreadyInUseExceptionWhenEmailIsDuplicated(): void
    {
        $this->expectException(EmailAlreadyInUseException::class);
        $this->expectExceptionMessage('Email is already in use.');

        $repository = $this->getRepository();
        $this->addEmailUniqueIndex();
        $email = EmailMother::create('duplicated@example.com');

        $repository->put(UserMother::create(email: $email));
        $repository->put(UserMother::create(email: $email));
    }

    /**
     * Reproduces the known limit (#121): the pre-check in CreateUserCommandHandler reads by email
     * before writing, so it does not see a soft-deleted user. The unique index does not filter by
     * deleted_at either, so the write itself must be the one to reject it.
     *
     * @throws Exception
     * @throws EmailAlreadyInUseException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     */
    public function testPutThrowsEmailAlreadyInUseExceptionWhenEmailBelongsToASoftDeletedUser(): void
    {
        $this->expectException(EmailAlreadyInUseException::class);
        $this->expectExceptionMessage('Email is already in use.');

        $repository = $this->getRepository();
        $this->addEmailUniqueIndex();
        $email = EmailMother::create('soft-deleted@example.com');

        $softDeletedUser = UserMother::create(email: $email);
        $repository->put($softDeletedUser);
        $repository->put(UserMother::create(
            id: $softDeletedUser->getId(),
            email: $email,
            deletedAt: new \DateTimeImmutable(),
        ));

        $repository->put(UserMother::create(email: $email));
    }
}
