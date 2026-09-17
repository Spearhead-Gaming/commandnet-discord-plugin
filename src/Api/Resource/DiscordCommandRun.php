<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use MajesticDev\Discord\Api\DTO\DiscordCommandResult;
use MajesticDev\Discord\Api\Processor\DiscordCommandRunProcessor;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [new Post(
        uriTemplate: '/discord/commands/run',
        processor: DiscordCommandRunProcessor::class,
        output: DiscordCommandResult::class,
    )]
)]
class DiscordCommandRun
{
    #[Groups('DiscordCommandRun')]
    public string $name;

    /** @var array<string, mixed> */
    #[Groups('DiscordCommandRun')]
    public array $options;

    #[Groups('DiscordCommandRun')]
    public string $discordUserId;

    /**
     * The guild the interaction was run in. Null for DM-run commands, which discord.js
     * itself reports as guildId: null - commands that need a unit/server context should
     * check for that instead of assuming it's always set.
     */
    #[Groups('DiscordCommandRun')]
    public ?string $guildId = null;
}
