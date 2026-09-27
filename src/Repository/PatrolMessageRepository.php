<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Repository;

use Forumify\Core\Repository\AbstractRepository;
use MajesticDev\Discord\Entity\PatrolMessage;

/**
 * @extends AbstractRepository<PatrolMessage>
 */
class PatrolMessageRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return PatrolMessage::class;
    }

    public function findForPatrol(int $operationId, string $guildId): ?PatrolMessage
    {
        return $this->findOneBy(['operationId' => $operationId, 'guildId' => $guildId]);
    }

    /**
     * @return array<PatrolMessage> every server's post for one patrol
     */
    public function findAllForPatrol(int $operationId): array
    {
        return $this->findBy(['operationId' => $operationId]);
    }
}
