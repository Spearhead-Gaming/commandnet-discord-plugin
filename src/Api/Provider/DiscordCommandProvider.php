<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use MajesticDev\Discord\Api\Resource\DiscordCommand;
use MajesticDev\Discord\Discord\DiscordCommandInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * @implements ProviderInterface<DiscordCommand>
 */
class DiscordCommandProvider implements ProviderInterface
{
    /**
     * @param iterable<DiscordCommandInterface> $commands
     */
    public function __construct(
        #[AutowireIterator('discord.command')]
        private readonly iterable $commands,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $commands = [];
        foreach ($this->commands as $command) {
            $cmd = new DiscordCommand();
            $cmd->name = $command->getName();
            $cmd->description = $command->getDescription();
            $cmd->options = $command->getOptions();

            $commands[] = $cmd;
        }

        return $commands;
    }
}
