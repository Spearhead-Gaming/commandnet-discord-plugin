<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Entity;

use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use MajesticDev\Discord\Repository\PatrolMessageRepository;

/**
 * Remembers where a patrol was posted in a Discord server, so the post can be edited when
 * people join or leave. The patrol is held as a plain id, not a relation: commandnet-plugin
 * is an optional dependency of this plugin, so nothing here may map onto its entities.
 */
#[ORM\Entity(PatrolMessageRepository::class)]
#[ORM\Table(name: 'discord_patrol_message')]
#[ORM\UniqueConstraint(name: 'discord_patrol_message_uniq', fields: ['operationId', 'guildId'])]
class PatrolMessage
{
    use IdentifiableEntityTrait;

    #[ORM\Column]
    private int $operationId;

    #[ORM\Column(length: 32)]
    private string $guildId;

    #[ORM\Column(length: 32)]
    private string $channelId;

    #[ORM\Column(length: 32)]
    private string $messageId;

    public function __construct(int $operationId, string $guildId, string $channelId, string $messageId)
    {
        $this->operationId = $operationId;
        $this->guildId = $guildId;
        $this->channelId = $channelId;
        $this->messageId = $messageId;
    }

    public function getOperationId(): int
    {
        return $this->operationId;
    }

    public function getGuildId(): string
    {
        return $this->guildId;
    }

    public function getChannelId(): string
    {
        return $this->channelId;
    }

    public function getMessageId(): string
    {
        return $this->messageId;
    }
}
