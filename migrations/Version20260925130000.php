<?php

declare(strict_types=1);

namespace ForumifyDiscordMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add a patrols channel to each Discord connection, and remember which message each patrol was posted as.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE discord_connection ADD patrols_channel_id VARCHAR(32) DEFAULT NULL');
        $this->addSql('CREATE TABLE discord_patrol_message (operation_id INT NOT NULL, guild_id VARCHAR(32) NOT NULL, channel_id VARCHAR(32) NOT NULL, message_id VARCHAR(32) NOT NULL, id INT AUTO_INCREMENT NOT NULL, UNIQUE INDEX discord_patrol_message_uniq (operation_id, guild_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE discord_patrol_message');
        $this->addSql('ALTER TABLE discord_connection DROP patrols_channel_id');
    }
}
