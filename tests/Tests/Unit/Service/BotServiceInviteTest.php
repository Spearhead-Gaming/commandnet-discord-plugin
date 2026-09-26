<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Tests\Unit\Service;

use Forumify\Core\Repository\SettingRepository;
use Forumify\OAuth\Repository\IdentityProviderUserRepository;
use Forumify\OAuth\Repository\OAuthClientRepository;
use MajesticDev\Discord\Repository\DiscordConnectionRepository;
use MajesticDev\Discord\Entity\DiscordConnection;
use MajesticDev\Discord\Exception\DiscordBotException;
use MajesticDev\Discord\Service\BotService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class BotServiceInviteTest extends TestCase
{
    /**
     * @param array<string, mixed>|DiscordBotException $answer
     */
    private function bot(array|DiscordBotException $answer): BotService
    {
        $bot = $this->getMockBuilder(BotService::class)->setConstructorArgs([
            $this->createStub(SettingRepository::class),
            $this->createStub(SerializerInterface::class),
            $this->createStub(OAuthClientRepository::class),
            $this->createStub(IdentityProviderUserRepository::class),
            $this->createStub(DiscordConnectionRepository::class),
            $this->createStub(LoggerInterface::class),
        ])->onlyMethods(['sendDataForResult'])->getMock();
        $bot->method('sendDataForResult')->willReturnCallback(
            static fn () => $answer instanceof DiscordBotException ? throw $answer : $answer,
        );
        return $bot;
    }

    private function connection(?string $channel): DiscordConnection
    {
        $connection = new DiscordConnection();
        $connection->setGuildId('g1');
        $connection->setInviteChannelId($channel);
        return $connection;
    }

    public function testCreateInviteReturnsTheUrl(): void
    {
        self::assertSame('https://discord.gg/a', $this->bot(['code' => 'a', 'url' => 'https://discord.gg/a'])->createInvite($this->connection('c1')));
    }

    public function testCreateInviteIsNullWithoutAChannelOrOnFailure(): void
    {
        self::assertNull($this->bot(['url' => 'x'])->createInvite($this->connection(null)));
        self::assertNull($this->bot(new DiscordBotException('down'))->createInvite($this->connection('c1')));
        self::assertNull($this->bot([])->createInvite($this->connection('c1')));
    }

    public function testSendDirectMessageOnlyTrueWhenTheBotSaysOk(): void
    {
        self::assertTrue($this->bot(['ok' => true])->sendDirectMessage('g1', '42', 'hi'));
        self::assertFalse($this->bot(['ok' => false, 'reason' => 'dms_closed'])->sendDirectMessage('g1', '42', 'hi'));
        self::assertFalse($this->bot(new DiscordBotException('down'))->sendDirectMessage('g1', '42', 'hi'));
    }
}
