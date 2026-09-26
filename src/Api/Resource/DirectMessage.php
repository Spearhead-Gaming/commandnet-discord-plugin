<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Direct-messages one Discord user. The bot answers {ok: true} or {ok: false, reason} rather
 * than failing when the DM can't be delivered. Recognised by the JSON-LD @type ("DirectMessage").
 */
#[ApiResource(operations: [])]
class DirectMessage
{
    #[ApiProperty(identifier: true)]
    public readonly int $id;

    #[Groups('DirectMessage')]
    public string $guildId;

    #[Groups('DirectMessage')]
    public string $discordUserId;

    #[Groups('DirectMessage')]
    public ?string $content = null;

    public function __construct()
    {
        $this->id = time();
    }
}
