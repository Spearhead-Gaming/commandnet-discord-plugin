<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Repository;

use Forumify\Core\Repository\AbstractRepository;
use MajesticDev\Discord\Entity\DiscordRoleMapping;

/**
 * @extends AbstractRepository<DiscordRoleMapping>
 */
class DiscordRoleMappingRepository extends AbstractRepository
{
    public static function getEntityClass(): string
    {
        return DiscordRoleMapping::class;
    }
}
