<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Deletes a message the bot posted earlier (the post of a patrol that was deleted). The bot
 * recognises it by this class's JSON-LD @type ("DeleteMessage").
 */
#[ApiResource(operations: [])]
class DeleteMessage
{
    #[ApiProperty(identifier: true)]
    public readonly int $id;

    #[Groups('DeleteMessage')]
    public string $guildId;

    #[Groups('DeleteMessage')]
    public string $channelId;

    #[Groups('DeleteMessage')]
    public string $messageId;

    public function __construct()
    {
        $this->id = time();
    }
}
