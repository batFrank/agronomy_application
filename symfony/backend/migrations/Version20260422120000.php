<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260422120000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE api_key (
                id UUID NOT NULL,
                api_key VARCHAR(255) NOT NULL,
                active BOOLEAN NOT NULL DEFAULT TRUE,
                user_id UUID DEFAULT NULL,
                created_at TIMESTAMP(6) NOT NULL,
                updated_at TIMESTAMP(6) DEFAULT NULL,
                deleted_at TIMESTAMP(6) DEFAULT NULL,
                PRIMARY KEY(id)
            )
        ');

        $this->addSql('CREATE UNIQUE INDEX UNIQ_api_key_api_key ON api_key (api_key)');
        $this->addSql('CREATE INDEX idx_api_key_deleted_at ON api_key (deleted_at)');
        $this->addSql('CREATE INDEX idx_api_key_user_id ON api_key (user_id)');
        $this->addSql('ALTER TABLE api_key ADD CONSTRAINT FK_api_key_user_id FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE api_key');
    }
}
