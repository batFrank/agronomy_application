<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260302092916 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add createdBy field to User entity for creator tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD created_by UUID DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_user_created_by ON "user" (created_by)');
        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT FK_user_created_by FOREIGN KEY (created_by) REFERENCES "user" (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT FK_user_created_by');
        $this->addSql('DROP INDEX idx_user_created_by');
        $this->addSql('ALTER TABLE "user" DROP created_by');
    }
}
