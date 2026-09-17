<?php

declare(strict_types=1);

namespace MajesticDev\Discord\EventSubscriber;

use MajesticDev\Discord\Exception\DiscordBotException;
use MajesticDev\Discord\CommandNetDiscordPlugin;
use MajesticDev\Discord\Service\BotService;
use Forumify\Plugin\Event\PluginRefreshedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(PluginRefreshedEvent::class)]
class RefreshFormsAndViewsListener
{
    public function __construct(
        private readonly BotService $botService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(PluginRefreshedEvent $event): void
    {
        if ($event->plugin->getPluginClass() !== CommandNetDiscordPlugin::class) {
            return;
        }

        try {
            $this->botService->fetchData('refreshSlashCommands');
        } catch (DiscordBotException $ex) {
            $this->logger->error('Unable to force slash command refresh.', [
                'exception' => $ex,
            ]);
        }
    }
}
