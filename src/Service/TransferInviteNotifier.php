<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Service;

use Forumify\Core\Entity\Notification;
use Forumify\Core\Notification\GenericEmailNotificationType;
use Forumify\Core\Notification\NotificationService;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Unit;
use MajesticDev\Discord\Entity\DiscordConnection;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Tells a soldier how to join their new unit's Discord server, with a Forumify notification
 * (in-app, plus an email when the member has email notifications on). Kept apart from
 * TransferInviteListener so a later phase can add a Discord DM channel - a single-use invite
 * asked of the bot, falling back to this when DMs are closed - without touching the rules
 * for when to notify.
 */
class TransferInviteNotifier
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function notify(SoldierProfile $soldier, Unit $unit, DiscordConnection $connection): void
    {
        $this->notificationService->sendNotification(new Notification(
            GenericEmailNotificationType::TYPE,
            $soldier->getUser(),
            [
                'title' => 'Join your unit\'s Discord server',
                'description' => sprintf(
                    'You have been assigned to %s. Use this link to join their Discord server (%s).',
                    $unit->getName(),
                    $connection->getLabel(),
                ),
                'url' => $this->urlGenerator->generate(
                    'discord_join',
                    ['guildId' => $connection->getGuildId()],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                ),
                'emailActionLabel' => 'Join Discord',
            ],
        ));
    }
}
