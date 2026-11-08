<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version202511111407 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE "user" (
                id UUID NOT NULL DEFAULT gen_random_uuid(),
                email VARCHAR(255) NOT NULL,
                roles JSONB NOT NULL DEFAULT \'[]\',
                password_hash VARCHAR(255) NOT NULL,
                created_at TIMESTAMP(6) NOT NULL,
                updated_at TIMESTAMP(6) DEFAULT NULL,
                deleted_at TIMESTAMP(6) DEFAULT NULL,
                password_reset_token VARCHAR(255) DEFAULT NULL,
                password_reset_token_expiration TIMESTAMP(6) DEFAULT NULL,
                PRIMARY KEY(id)
            )
        ');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_user_email ON "user" (email)');
        $this->addSql('CREATE INDEX idx_user_deleted_at ON "user" (deleted_at)');

        $this->addSql('
            CREATE TABLE refresh_token (
                refresh_token VARCHAR(128) NOT NULL,
                username VARCHAR(255) NOT NULL,
                valid TIMESTAMP(6) NOT NULL,
                PRIMARY KEY(refresh_token)
            )
        ');
        $this->addSql('CREATE INDEX idx_username ON refresh_token (username)');

        $this->addSql('
            CREATE TABLE cache_items (
                item_id BYTEA NOT NULL,
                item_data BYTEA NOT NULL,
                item_lifetime INT DEFAULT NULL,
                item_time INT NOT NULL,
                PRIMARY KEY(item_id)
            )
        ');

        $this->addSql('
            CREATE TABLE scheduled_task (
                id UUID NOT NULL DEFAULT gen_random_uuid(),
                name VARCHAR(255) NOT NULL,
                frequency VARCHAR(255) NOT NULL,
                message_class VARCHAR(255) NOT NULL,
                enabled BOOLEAN NOT NULL DEFAULT TRUE,
                created_at TIMESTAMP(6) NOT NULL,
                updated_at TIMESTAMP(6) DEFAULT NULL,
                deleted_at TIMESTAMP(6) DEFAULT NULL,
                PRIMARY KEY(id)
            )
        ');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_scheduled_task_name ON scheduled_task (name)');
        $this->addSql('CREATE INDEX idx_scheduled_task_enabled ON scheduled_task (enabled)');
        $this->addSql('CREATE INDEX idx_scheduled_task_deleted_at ON scheduled_task (deleted_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE refresh_token');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE scheduled_task');
        $this->addSql('DROP TABLE cache_items');
    }
}
