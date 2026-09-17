<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Discord;

use MajesticDev\Discord\Api\DTO\DiscordCommandResult;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use MajesticDev\Discord\Api\DTO\DiscordCommandOption;
use MajesticDev\Discord\Api\Resource\DiscordCommandRun;

#[AutoconfigureTag('discord.command')]
interface DiscordCommandInterface
{
    public function getName(): string;

    public function getDescription(): string;

    /**
     * @return list<DiscordCommandOption>
     */
    public function getOptions(): array;

    public function run(DiscordCommandRun $command): DiscordCommandResult;
}
