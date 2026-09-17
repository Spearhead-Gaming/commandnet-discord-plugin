<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Posts a plain message to one channel of one connection's server. The bot recognises
 * it by this class's JSON-LD @type ("PostMessage"), the same way it recognises
 * RolesChanged and UsernameChanged.
 */
#[ApiResource(operations: [])]
class PostMessage
{
    #[ApiProperty(identifier: true)]
    public readonly int $id;

    #[Groups('PostMessage')]
    public string $guildId;

    #[Groups('PostMessage')]
    public string $channelId;

    #[Groups('PostMessage')]
    public ?string $content = null;

    /** @var array<string, mixed>|null */
    #[Groups('PostMessage')]
    public ?array $embed = null;

    public function __construct()
    {
        $this->id = time();
    }
}
