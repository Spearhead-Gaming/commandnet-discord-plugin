<?php

declare(strict_types=1);

namespace ForumifyDiscordMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260917150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add discord_connection and discord_role_mapping, replacing the single global guild assumption with one connection per Discord server.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE discord_connection (guild_id VARCHAR(32) NOT NULL, label VARCHAR(150) NOT NULL, invite_link VARCHAR(255) DEFAULT NULL, ops_log_channel_id VARCHAR(32) DEFAULT NULL, announcements_channel_id VARCHAR(32) DEFAULT NULL, active TINYINT NOT NULL, id INT AUTO_INCREMENT NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_discord_connection_guild_id (guild_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE discord_role_mapping (discord_role_id VARCHAR(32) NOT NULL, id INT AUTO_INCREMENT NOT NULL, connection_id INT NOT NULL, forumify_role_id INT NOT NULL, INDEX IDX_discord_role_mapping_connection (connection_id), INDEX IDX_discord_role_mapping_role (forumify_role_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE discord_role_mapping ADD CONSTRAINT FK_discord_role_mapping_connection FOREIGN KEY (connection_id) REFERENCES discord_connection (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE discord_role_mapping ADD CONSTRAINT FK_discord_role_mapping_role FOREIGN KEY (forumify_role_id) REFERENCES role (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE discord_role_mapping DROP FOREIGN KEY FK_discord_role_mapping_connection');
        $this->addSql('ALTER TABLE discord_role_mapping DROP FOREIGN KEY FK_discord_role_mapping_role');
        $this->addSql('DROP TABLE discord_role_mapping');
        $this->addSql('DROP TABLE discord_connection');
    }
}
