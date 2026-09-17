<?php

declare(strict_types=1);

namespace Forumify\Discord\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(operations: [])]
class RolesChanged
{
    #[ApiProperty(identifier: true)]
    public readonly int $id;

    #[Groups('RolesChanged')]
    public string $guildId;

    #[Groups('RolesChanged')]
    public string $discordIdentifier;

    /** @var array<string> */
    #[Groups('RolesChanged')]
    public array $rolesAdded = [];

    /** @var array<string> */
    #[Groups('RolesChanged')]
    public array $rolesRemoved = [];

    public function __construct()
    {
        $this->id = time();
    }
}
