<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Asks the bot for an invite to one channel of one server. The bot answers {code, url}; it
 * recognises the request by this class's JSON-LD @type ("CreateInvite"). Defaults on the bot's
 * side are single-use and 7 days.
 */
#[ApiResource(operations: [])]
class CreateInvite
{
    #[ApiProperty(identifier: true)]
    public readonly int $id;

    #[Groups('CreateInvite')]
    public string $guildId;

    #[Groups('CreateInvite')]
    public string $channelId;

    #[Groups('CreateInvite')]
    public ?int $maxAgeSeconds = null;

    #[Groups('CreateInvite')]
    public ?int $maxUses = null;

    #[Groups('CreateInvite')]
    public ?string $reason = null;

    public function __construct()
    {
        $this->id = time();
    }
}
