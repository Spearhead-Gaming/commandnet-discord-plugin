<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Service;

use Forumify\Core\Entity\User;
use Forumify\OAuth\Idp\DiscordIdp;
use Forumify\OAuth\Repository\IdentityProviderUserRepository;
use MajesticDev\CommandNet\Entity\Enum\AarStatus;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Service\PatrolReminderNotifier;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

/**
 * The Discord half of PatrolAarReminders' due/overdue reminder - see that interface. Posts
 * to the community announcements channel (D6), mentioning the leader by their linked
 * Discord account when they have one; the forum notification PatrolAarReminders already
 * sends is the fallback when they don't.
 */
#[AsDecorator('MajesticDev\CommandNet\Service\PatrolReminderNotifier')]
class DiscordPatrolReminderNotifier implements PatrolReminderNotifier
{
    public function __construct(
        private readonly BotService $botService,
        private readonly IdentityProviderUserRepository $idpUserRepository,
    ) {
    }

    public function notify(Operation $patrol, AarStatus $status): void
    {
        $leader = $patrol->getLeader();
        if ($leader === null) {
            return;
        }

        $mention = $this->mentionFor($leader);
        if ($mention === null) {
            return;
        }

        $label = $status === AarStatus::OVERDUE ? 'AAR overdue' : 'AAR due';
        $this->botService->postAnnouncement(sprintf('**%s**: %s - %s', $label, $patrol->getTitle(), $mention));
    }

    private function mentionFor(User $leader): ?string
    {
        $idpUsers = $this->idpUserRepository->findByUserAndIdpType($leader, DiscordIdp::getType());
        $idpUser = reset($idpUsers);

        return $idpUser !== false ? '<@' . $idpUser->getExternalIdentifier() . '>' : null;
    }
}
