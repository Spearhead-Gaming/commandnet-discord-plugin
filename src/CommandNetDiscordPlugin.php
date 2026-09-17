<?php

declare(strict_types=1);

namespace MajesticDev\Discord;

use Forumify\Plugin\AbstractForumifyPlugin;
use Forumify\Plugin\PluginMetadata;

class CommandNetDiscordPlugin extends AbstractForumifyPlugin
{
    public function getPluginMetadata(): PluginMetadata
    {
        return new PluginMetadata(
            'Discord',
            'MajesticDev',
            'Multi-guild Discord bridge: syncs roles, usernames, slash commands and notifications to one connection per Discord server.',
            // TODO: replace with real domain once purchased
            'https://example.com',
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
