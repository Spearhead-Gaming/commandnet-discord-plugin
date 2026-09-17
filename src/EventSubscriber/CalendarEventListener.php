<?php

declare(strict_types=1);

namespace MajesticDev\Discord\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Forumify\Calendar\Entity\Calendar;
use Forumify\Calendar\Entity\CalendarEvent;
use Forumify\Core\Repository\SettingRepository;
use MajesticDev\Discord\Service\BotService;

/**
 * Cross-posts a new CalendarEvent to every connection's announcements channel, if its
 * calendar is one of the synced ones. Previously sent the raw CalendarEvent entity
 * straight to the bot with no @type/guildId the bot could act on - now goes through
 * BotService::postAnnouncement() like everything else, which is guild-aware and knows
 * how to shape a message the bot actually understands.
 */
#[AsEntityListener(Events::postPersist, method: 'postPersist', entity: CalendarEvent::class)]
class CalendarEventListener
{
    public function __construct(
        private readonly BotService $botService,
        private readonly SettingRepository $settingRepository,
    ) {
    }

    public function postPersist(CalendarEvent $event): void
    {
        if (!$this->shouldSyncCalendar($event->getCalendar())) {
            return;
        }

        $this->botService->postAnnouncement($this->formatMessage($event));
    }

    private function formatMessage(CalendarEvent $event): string
    {
        return \sprintf(
            '**%s**%s%s',
            $event->getTitle(),
            "\n" . $event->getStart()->format('l, F j, Y \a\t g:i A T'),
            $event->getContent() !== '' ? "\n" . $event->getContent() : '',
        );
    }

    private function shouldSyncCalendar(Calendar $calendar): bool
    {
        $calendarsToSync = $this->settingRepository->get('discord.calendars');
        if (empty($calendarsToSync)) {
            return false;
        }

        foreach ($calendarsToSync as $toSyncId) {
            if ($toSyncId === '*') {
                return true;
            }

            if ((int)$toSyncId === $calendar->getId()) {
                return true;
            }
        }
        return false;
    }
}
