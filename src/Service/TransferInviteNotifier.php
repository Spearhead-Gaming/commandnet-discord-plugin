<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Service;

use Forumify\Core\Entity\Notification;
use Forumify\Core\Notification\GenericEmailNotificationType;
use Forumify\Core\Notification\NotificationService;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Unit;
use Forumify\OAuth\Idp\DiscordIdp;
use Forumify\OAuth\Repository\IdentityProviderUserRepository;
use MajesticDev\Discord\Entity\DiscordConnection;
use MajesticDev\Discord\Exception\DiscordBotException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Tells a soldier how to join their new unit's Discord server. Preferably by Discord DM with a
 * single-use, expiring invite (the permanent public link would let anyone holding it into a
 * private unit server); when that isn't possible - no invite channel, no linked Discord
 * account, DMs closed, bot offline - with a Forumify notification (in-app, plus an email when
 * the member has email notifications on) linking to the permanent join page. One or the
 * other, never both. Kept apart from TransferInviteListener, which decides when to notify.
 */
class TransferInviteNotifier
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly BotService $botService,
        private readonly IdentityProviderUserRepository $idpUserRepository,
    ) {
    }

    public function notify(SoldierProfile $soldier, Unit $unit, DiscordConnection $connection): void
    {
        $discordUserId = $this->linkedDiscordId($soldier);
        if ($discordUserId !== null && $this->alreadyInGuild($connection, $discordUserId)) {
            return;
        }

        if ($discordUserId !== null && $this->sendInviteDm($discordUserId, $unit, $connection)) {
            return;
        }

        $this->sendNotification($soldier, $unit, $connection);
    }

    private function linkedDiscordId(SoldierProfile $soldier): ?string
    {
        $idpUsers = $this->idpUserRepository->findByUserAndIdpType($soldier->getUser(), DiscordIdp::getType());
        $idpUser = reset($idpUsers);

        return $idpUser !== false ? $idpUser->getExternalIdentifier() : null;
    }

    private function alreadyInGuild(DiscordConnection $connection, string $discordUserId): bool
    {
        try {
            foreach ($this->botService->getGuildMembers($connection->getGuildId()) as $member) {
                if ($member['id'] === $discordUserId) {
                    return true;
                }
            }
        } catch (DiscordBotException) {
            // Can't tell; invite them anyway.
        }
        return false;
    }

    private function sendInviteDm(string $discordUserId, Unit $unit, DiscordConnection $connection): bool
    {
        $invite = $this->botService->createInvite($connection);
        if ($invite === null) {
            return false;
        }

        return $this->botService->sendDirectMessage($connection->getGuildId(), $discordUserId, sprintf(
            "Welcome to %s! Here's your personal invite to your unit's Discord server. It works once and expires in 7 days: %s",
            $unit->getName(),
            $invite,
        ));
    }

    private function sendNotification(SoldierProfile $soldier, Unit $unit, DiscordConnection $connection): void
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
