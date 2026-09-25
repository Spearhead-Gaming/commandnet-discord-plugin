<?php

declare(strict_types=1);

namespace MajesticDev\Discord\EventSubscriber;

use DateTime;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use MajesticDev\CommandNet\Entity\Assignment;
use MajesticDev\Discord\Repository\DiscordConnectionRepository;
use MajesticDev\Discord\Service\TransferInviteNotifier;

/**
 * When a soldier gets a new primary assignment (a transfer, or their first posting on
 * enlistment) to a unit with its own Discord server, sends them that server's invite.
 * postPersist only fires once per assignment, so nobody is notified twice.
 */
#[AsEntityListener(Events::postPersist, method: 'postPersist', entity: Assignment::class)]
class TransferInviteListener
{
    /** Older start dates are backfilled history (imports, corrections), not a transfer. */
    public const string MAX_AGE = '-7 days';

    public function __construct(
        private readonly DiscordConnectionRepository $connectionRepository,
        private readonly TransferInviteNotifier $notifier,
    ) {
    }

    public function postPersist(Assignment $assignment): void
    {
        if (!$assignment->isPrimary() || !$assignment->isActive()) {
            return;
        }
        if ($assignment->getStartDate() < new DateTime('today ' . self::MAX_AGE)) {
            return;
        }

        $unit = $assignment->getUnit();
        $guildId = $unit->getDiscordGuildId();
        if ($guildId === null || $guildId === '') {
            return;
        }

        $connection = $this->connectionRepository->findByGuildId($guildId);
        if ($connection === null || !$connection->isActive() || empty($connection->getInviteLink())) {
            return;
        }

        $this->notifier->notify($assignment->getSoldier(), $unit, $connection);
    }
}
