<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Tests\Unit\Service;

use Forumify\Core\Entity\User;
use Forumify\Core\Notification\NotificationService;
use Forumify\OAuth\Entity\IdentityProviderUser;
use Forumify\OAuth\Repository\IdentityProviderUserRepository;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Unit;
use MajesticDev\Discord\Entity\DiscordConnection;
use MajesticDev\Discord\Exception\DiscordBotException;
use MajesticDev\Discord\Service\BotService;
use MajesticDev\Discord\Service\TransferInviteNotifier;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TransferInviteNotifierTest extends TestCase
{
    private NotificationService&MockObject $notifications;
    private BotService&MockObject $bot;
    private IdentityProviderUserRepository $idpUsers;

    protected function setUp(): void
    {
        // commandnet-plugin is an optional, un-required dependency (see phpstan.neon).
        if (!class_exists(Unit::class)) {
            $this->markTestSkipped('commandnet-plugin is not installed.');
        }
        $this->notifications = $this->createMock(NotificationService::class);
        $this->bot = $this->createMock(BotService::class);
        $this->idpUsers = $this->createStub(IdentityProviderUserRepository::class);
    }

    private function link(?string $discordId): void
    {
        $idpUsers = [];
        if ($discordId !== null) {
            $idpUser = $this->createStub(IdentityProviderUser::class);
            $idpUser->method('getExternalIdentifier')->willReturn($discordId);
            $idpUsers = [$idpUser];
        }
        $this->idpUsers->method('findByUserAndIdpType')->willReturn($idpUsers);
    }

    private function notify(?string $inviteChannel = 'c1'): void
    {
        $unit = new Unit();
        $unit->setName('1st Platoon');
        $connection = new DiscordConnection();
        $connection->setGuildId('g1');
        $connection->setLabel('1st Platoon');
        $connection->setInviteChannelId($inviteChannel);
        $soldier = $this->createStub(SoldierProfile::class);
        $soldier->method('getUser')->willReturn(new User());
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('https://forum.test/discord/join/g1');

        (new TransferInviteNotifier($this->notifications, $urls, $this->bot, $this->idpUsers))
            ->notify($soldier, $unit, $connection);
    }

    public function testADeliveredDmReplacesTheNotification(): void
    {
        $this->link('42');
        $this->bot->method('getGuildMembers')->willReturn([]);
        $this->bot->method('createInvite')->willReturn('https://discord.gg/abc');
        $this->bot->expects($this->once())->method('sendDirectMessage')
            ->with('g1', '42', $this->stringContains('https://discord.gg/abc'))
            ->willReturn(true);
        $this->notifications->expects($this->never())->method('sendNotification');

        $this->notify();
    }

    public function testFailedInviteFallsBackToTheNotification(): void
    {
        $this->link('42');
        $this->bot->method('getGuildMembers')->willReturn([]);
        $this->bot->method('createInvite')->willReturn(null);
        $this->bot->expects($this->never())->method('sendDirectMessage');
        $this->notifications->expects($this->once())->method('sendNotification');

        $this->notify();
    }

    public function testClosedDmsFallBackToTheNotification(): void
    {
        $this->link('42');
        $this->bot->method('getGuildMembers')->willReturn([]);
        $this->bot->method('createInvite')->willReturn('https://discord.gg/abc');
        $this->bot->method('sendDirectMessage')->willReturn(false);
        $this->notifications->expects($this->once())->method('sendNotification');

        $this->notify();
    }

    public function testNoLinkedAccountFallsBackWithoutTouchingTheBot(): void
    {
        $this->link(null);
        $this->bot->expects($this->never())->method('createInvite');
        $this->bot->expects($this->never())->method('sendDirectMessage');
        $this->notifications->expects($this->once())->method('sendNotification');

        $this->notify();
    }

    public function testNoInviteChannelFallsBack(): void
    {
        $this->link('42');
        $this->bot->method('getGuildMembers')->willReturn([]);
        // The real BotService returns null without a channel; the stub mirrors that.
        $this->bot->method('createInvite')->willReturn(null);
        $this->bot->expects($this->never())->method('sendDirectMessage');
        $this->notifications->expects($this->once())->method('sendNotification');

        $this->notify(null);
    }

    public function testAMemberAlreadyInTheServerIsLeftAlone(): void
    {
        $this->link('42');
        $this->bot->method('getGuildMembers')->willReturn([['id' => '42', 'username' => 'u', 'displayName' => 'U']]);
        $this->bot->expects($this->never())->method('createInvite');
        $this->notifications->expects($this->never())->method('sendNotification');

        $this->notify();
    }

    public function testAnUnreachableBotStillFallsBack(): void
    {
        $this->link('42');
        $this->bot->method('getGuildMembers')->willThrowException(new DiscordBotException('down'));
        $this->bot->method('createInvite')->willReturn(null);
        $this->notifications->expects($this->once())->method('sendNotification');

        $this->notify();
    }
}
