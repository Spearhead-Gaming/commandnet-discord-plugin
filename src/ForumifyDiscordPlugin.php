<?php

declare(strict_types=1);

namespace Forumify\Discord;

use Forumify\Plugin\AbstractForumifyPlugin;
use Forumify\Plugin\PluginMetadata;

class ForumifyDiscordPlugin extends AbstractForumifyPlugin
{
    public function getPluginMetadata(): PluginMetadata
    {
        return new PluginMetadata(
            'Discord',
            'forumify',
            'Multi-guild Discord bridge: syncs roles, usernames, slash commands and notifications to one connection per Discord server.',
            'https://forumify.net',
            settingsRoute: 'discord_admin_settings',
        );
    }

    public function getPermissions(): array
    {
        return [
            'admin' => [
                'connections' => ['view', 'manage'],
            ],
        ];
    }
}
