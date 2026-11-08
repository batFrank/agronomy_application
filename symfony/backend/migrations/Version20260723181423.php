<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260723181423 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add revoked field to otp entity';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "otp" ADD revoked BOOLEAN DEFAULT false');
        $this->addSql('CREATE INDEX idx_otp_revoked ON "otp" (revoked)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_otp_revoked');
        $this->addSql('ALTER TABLE "otp" DROP revoked');
    }
}
