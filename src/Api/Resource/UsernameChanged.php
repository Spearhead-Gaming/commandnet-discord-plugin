<?php

declare(strict_types=1);

namespace Forumify\Discord\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(operations: [])]
class UsernameChanged
{
    #[ApiProperty(identifier: true)]
    public readonly int $id;

    #[Groups('UsernameChanged')]
    public string $guildId;

    #[Groups('UsernameChanged')]
    public string $discordIdentifier;

    #[Groups('UsernameChanged')]
    public string $discordUsername;

    #[Groups('UsernameChanged')]
    public string $newUsername;

    public function __construct()
    {
        $this->id = time();
    }
}
