<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Entity;

use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\Role;
use MajesticDev\Discord\Repository\DiscordRoleMappingRepository;

/**
 * "Grant/revoke this Discord role on this connection's server whenever a user gains or
 * loses this forumify role" - scoped per connection so the same forumify Role (e.g. a
 * Unit's role from commandnet-plugin) can map to a different Discord role snowflake in
 * each unit's own server.
 */
#[ORM\Entity(DiscordRoleMappingRepository::class)]
class DiscordRoleMapping
{
    use IdentifiableEntityTrait;

    #[ORM\ManyToOne(targetEntity: DiscordConnection::class, inversedBy: 'roleMappings')]
    #[ORM\JoinColumn(name: 'connection_id', nullable: false, onDelete: 'CASCADE')]
    private ?DiscordConnection $connection = null;

    #[ORM\ManyToOne(targetEntity: Role::class)]
    #[ORM\JoinColumn(name: 'forumify_role_id', nullable: false, onDelete: 'CASCADE')]
    private ?Role $forumifyRole = null;

    #[ORM\Column(length: 32)]
    private string $discordRoleId = '';

    public function getConnection(): ?DiscordConnection
    {
        return $this->connection;
    }

    public function setConnection(?DiscordConnection $connection): void
    {
        $this->connection = $connection;
    }

    public function getForumifyRole(): ?Role
    {
        return $this->forumifyRole;
    }

    public function setForumifyRole(?Role $forumifyRole): void
    {
        $this->forumifyRole = $forumifyRole;
    }

    public function getDiscordRoleId(): string
    {
        return $this->discordRoleId;
    }

    public function setDiscordRoleId(string $discordRoleId): void
    {
        $this->discordRoleId = $discordRoleId;
    }
}
