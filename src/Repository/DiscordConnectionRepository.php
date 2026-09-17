<?php

declare(strict_types=1);

namespace Forumify\Discord\Repository;

use Forumify\Core\Repository\AbstractRepository;
use Forumify\Discord\Entity\DiscordConnection;

/**
 * @extends AbstractRepository<DiscordConnection>
 */
class DiscordConnectionRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return DiscordConnection::class;
    }

    /**
     * @return DiscordConnection[]
     */
    public function findActive(): array
    {
        return $this->findBy(['active' => true]);
    }

    public function findByGuildId(string $guildId): ?DiscordConnection
    {
        return $this->findOneBy(['guildId' => $guildId]);
    }
}
