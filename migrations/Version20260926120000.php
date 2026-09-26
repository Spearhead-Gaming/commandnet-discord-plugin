<?php

declare(strict_types=1);

namespace ForumifyDiscordMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add an invite channel to each Discord connection, for single-use invites sent by DM.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE discord_connection ADD invite_channel_id VARCHAR(32) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE discord_connection DROP invite_channel_id');
    }
}
