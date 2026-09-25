<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Changes a message the bot posted earlier (a patrol post whose attendee list moved). Only
 * the parts that are set are changed; an empty components array removes the buttons. The bot
 * recognises it by this class's JSON-LD @type ("EditMessage").
 */
#[ApiResource(operations: [])]
class EditMessage
{
    #[ApiProperty(identifier: true)]
    public readonly int $id;

    #[Groups('EditMessage')]
    public string $guildId;

    #[Groups('EditMessage')]
    public string $channelId;

    #[Groups('EditMessage')]
    public string $messageId;

    #[Groups('EditMessage')]
    public ?string $content = null;

    /** @var array<string, mixed>|null */
    #[Groups('EditMessage')]
    public ?array $embed = null;

    /** @var array<int, array<string, mixed>>|null */
    #[Groups('EditMessage')]
    public ?array $components = null;

    public function __construct()
    {
        $this->id = time();
    }
}
