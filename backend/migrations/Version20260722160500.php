<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260722160500 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE "otp" (
                id UUID NOT NULL DEFAULT gen_random_uuid(),
                resource_id UUID NOT NULL,
                resource_type VARCHAR(50) NOT NULL,
                purpose VARCHAR(50) NOT NULL,
                code_secret CHAR(64) NOT NULL,
                attempts SMALLINT NOT NULL DEFAULT 1,
                max_attempts SMALLINT NOT NULL DEFAULT 1,
                expires_at TIMESTAMP(6) NOT NULL,
                consumed_at TIMESTAMP(6) DEFAULT NULL,
                created_at TIMESTAMP(6) NOT NULL,
                PRIMARY KEY(id)
            )
        ');
        $this->addSql('CREATE INDEX idx_otp_resource_and_purpose ON otp (resource_id, resource_type, purpose)');
        $this->addSql('CREATE INDEX idx_otp_code_secret ON otp (code_secret)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE "otp"');
    }
}
