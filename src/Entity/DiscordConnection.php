<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\TimestampableEntityTrait;
use MajesticDev\Discord\Repository\DiscordConnectionRepository;

/**
 * One Discord server the bot has been invited to - the community server, or a unit's
 * private one. Role mappings and channel targets are scoped to a connection instead of
 * being one flat global setting, which is what makes the bot multi-guild: BotService
 * figures out which connection(s) a change belongs to by walking this table rather than
 * being told a guild id by its caller.
 */
#[ORM\Entity(DiscordConnectionRepository::class)]
class DiscordConnection
{
    use IdentifiableEntityTrait;
    use TimestampableEntityTrait;

    /**
     * The Discord server's snowflake ID (right-click the server icon -> Copy Server ID,
     * with Developer Mode enabled).
     */
    #[ORM\Column(length: 32, unique: true)]
    private string $guildId = '';

    #[ORM\Column(length: 150)]
    private string $label = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $inviteLink = null;

    /**
     * Channel snowflake for infra-type notifications (server manager, S3 tools, ...).
     */
    #[ORM\Column(length: 32, nullable: true)]
    private ?string $opsLogChannelId = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $announcementsChannelId = null;

    /**
     * Where new patrols are posted, with their Join / Leave / Submit AAR buttons.
     */
    #[ORM\Column(length: 32, nullable: true)]
    private ?string $patrolsChannelId = null;

    #[ORM\Column]
    private bool $active = true;

    /** @var Collection<int, DiscordRoleMapping> */
    #[ORM\OneToMany(mappedBy: 'connection', targetEntity: DiscordRoleMapping::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $roleMappings;

    public function __construct()
    {
        $this->roleMappings = new ArrayCollection();
    }

    public function getGuildId(): string
    {
        return $this->guildId;
    }

    public function setGuildId(string $guildId): void
    {
        $this->guildId = $guildId;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getInviteLink(): ?string
    {
        return $this->inviteLink;
    }

    public function setInviteLink(?string $inviteLink): void
    {
        $this->inviteLink = $inviteLink;
    }

    public function getOpsLogChannelId(): ?string
    {
        return $this->opsLogChannelId;
    }

    public function setOpsLogChannelId(?string $opsLogChannelId): void
    {
        $this->opsLogChannelId = $opsLogChannelId;
    }

    public function getAnnouncementsChannelId(): ?string
    {
        return $this->announcementsChannelId;
    }

    public function setAnnouncementsChannelId(?string $announcementsChannelId): void
    {
        $this->announcementsChannelId = $announcementsChannelId;
    }

    public function getPatrolsChannelId(): ?string
    {
        return $this->patrolsChannelId;
    }

    public function setPatrolsChannelId(?string $patrolsChannelId): void
    {
        $this->patrolsChannelId = $patrolsChannelId;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    /**
     * @return Collection<int, DiscordRoleMapping>
     */
    public function getRoleMappings(): Collection
    {
        return $this->roleMappings;
    }

    public function addRoleMapping(DiscordRoleMapping $roleMapping): void
    {
        if ($this->roleMappings->contains($roleMapping)) {
            return;
        }

        $this->roleMappings->add($roleMapping);
        $roleMapping->setConnection($this);
    }

    public function removeRoleMapping(DiscordRoleMapping $roleMapping): void
    {
        if (!$this->roleMappings->removeElement($roleMapping)) {
            return;
        }

        if ($roleMapping->getConnection() === $this) {
            $roleMapping->setConnection(null);
        }
    }

    public function __toString(): string
    {
        return $this->label;
    }
}
