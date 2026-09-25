<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Tests\Unit\EventSubscriber;

use DateTime;
use MajesticDev\CommandNet\Entity\Enum\OperationType;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\Discord\EventSubscriber\PatrolAnnouncementListener;
use MajesticDev\Discord\Exception\DiscordBotException;
use MajesticDev\Discord\Service\BotService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PatrolAnnouncementListenerTest extends TestCase
{
    protected function setUp(): void
    {
        // commandnet-plugin is an optional, un-required dependency (see phpstan.neon).
        if (!class_exists(Operation::class)) {
            $this->markTestSkipped('commandnet-plugin is not installed.');
        }
    }

    private function patrol(): Operation
    {
        $patrol = new Operation();
        $patrol->setType(OperationType::PATROL);
        $patrol->setTitle('Night patrol');
        $patrol->setStartDateTime(new DateTime('2026-09-26 20:00'));
        return $patrol;
    }

    public function testAnAnnouncementFailureIsLoggedNotThrown(): void
    {
        $bot = $this->createStub(BotService::class);
        $bot->method('postAnnouncement')->willThrowException(new DiscordBotException('bot down'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        (new PatrolAnnouncementListener($bot, $logger))->postPersist($this->patrol());
    }

    public function testAPatrolIsAnnounced(): void
    {
        $bot = $this->createMock(BotService::class);
        $bot->expects(self::once())->method('postAnnouncement')
            ->with(self::stringContains('Night patrol'));

        (new PatrolAnnouncementListener($bot, $this->createStub(LoggerInterface::class)))
            ->postPersist($this->patrol());
    }
}
