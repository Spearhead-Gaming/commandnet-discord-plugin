<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use MajesticDev\Discord\Api\Provider\DiscordCommandProvider;
use MajesticDev\Discord\Api\DTO\DiscordCommandOption;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [new GetCollection(
        uriTemplate: '/discord/commands',
        provider: DiscordCommandProvider::class,
    )],
)]
class DiscordCommand
{
    #[Groups('DiscordCommand')]
    #[ApiProperty(identifier: true)]
    public string $name;

    #[Groups('DiscordCommand')]
    public string $description;

    /** @var array<DiscordCommandOption> */
    #[Groups('DiscordCommand')]
    public array $options;
}
