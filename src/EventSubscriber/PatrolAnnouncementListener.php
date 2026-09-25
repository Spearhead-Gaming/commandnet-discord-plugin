<?php

declare(strict_types=1);

namespace MajesticDev\Discord\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use MajesticDev\CommandNet\Entity\Enum\OperationType;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\Discord\Exception\DiscordBotException;
use MajesticDev\Discord\Service\BotService;
use Psr\Log\LoggerInterface;

/**
 * Cross-posts a newly-created patrol to every connection's announcements channel, the same
 * way CalendarEventListener does for synced calendar events - and for the same reason
 * patrols stay off synced calendars (see the spec's risk table), this is the one place
 * that decides "a patrol was just created", whether it was posted from the web or from
 * /command-net-patrol-create.
 *
 * D6 settled on the community server only for Phase B, so this always broadcasts rather
 * than trying to target the leader's own unit server.
 */
#[AsEntityListener(Events::postPersist, method: 'postPersist', entity: Operation::class)]
class PatrolAnnouncementListener
{
    public function __construct(
        private readonly BotService $botService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function postPersist(Operation $operation): void
    {
        if ($operation->getType() !== OperationType::PATROL) {
            return;
        }

        // postPersist runs inside the flush transaction, so an exception here would roll the
        // patrol back. A Discord outage must never stop a patrol being created.
        try {
            $this->botService->postAnnouncement($this->formatMessage($operation));
        } catch (DiscordBotException $ex) {
            $this->logger->error('Could not announce the new patrol to Discord.', ['exception' => $ex]);
        }
    }

    private function formatMessage(Operation $operation): string
    {
        $leader = $operation->getLeader();

        return sprintf(
            "**New patrol: %s**\n%s%s%s",
            $operation->getTitle(),
            $operation->getStartDateTime()->format('l, F j, Y \a\t g:i A'),
            $operation->getLocation() !== null ? ' - ' . $operation->getLocation() : '',
            $leader !== null ? "\nLed by " . $leader->getDisplayName() : '',
        );
    }
}
