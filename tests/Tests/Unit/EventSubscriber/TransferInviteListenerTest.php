<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Tests\Unit\EventSubscriber;

use DateTime;
use MajesticDev\CommandNet\Entity\Assignment;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Entity\Unit;
use MajesticDev\Discord\Entity\DiscordConnection;
use MajesticDev\Discord\EventSubscriber\TransferInviteListener;
use MajesticDev\Discord\Repository\DiscordConnectionRepository;
use MajesticDev\Discord\Service\TransferInviteNotifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TransferInviteListenerTest extends TestCase
{
    /**
     * @return iterable<string, array{bool, callable(Assignment, DiscordConnection, Unit): void}>
     */
    public static function cases(): iterable
    {
        yield 'primary active recent assignment notifies' => [true, static fn () => null];
        yield 'secondary assignment' => [false, static fn (Assignment $a) => $a->setIsPrimary(false)];
        yield 'ended assignment' => [false, static fn (Assignment $a) => $a->setEndDate(new DateTime())];
        yield 'backfilled start date' => [false, static fn (Assignment $a) => $a->setStartDate(new DateTime('-30 days'))];
        yield 'unit without a guild id' => [false, static fn (Assignment $a, DiscordConnection $c, Unit $u) => $u->setDiscordGuildId(null)];
        yield 'inactive connection' => [false, static fn (Assignment $a, DiscordConnection $c) => $c->setActive(false)];
        yield 'connection without an invite' => [false, static fn (Assignment $a, DiscordConnection $c) => $c->setInviteLink(null)];
    }

    /**
     * @param callable(Assignment, DiscordConnection, Unit): void $tweak
     */
    #[DataProvider('cases')]
    public function testNotifiesOnlyForRecentPrimaryAssignmentToUnitWithInvite(bool $expected, callable $tweak): void
    {
        $unit = new Unit();
        $unit->setDiscordGuildId('123');
        $connection = new DiscordConnection();
        $connection->setGuildId('123');
        $connection->setInviteLink('abc');
        $assignment = new Assignment($this->createStub(SoldierProfile::class), $unit);
        $tweak($assignment, $connection, $unit);

        $repository = $this->createStub(DiscordConnectionRepository::class);
        $repository->method('findByGuildId')->willReturn($connection);
        $notifier = $this->createMock(TransferInviteNotifier::class);
        $notifier->expects($expected ? $this->once() : $this->never())->method('notify');

        (new TransferInviteListener($repository, $notifier))->postPersist($assignment);
    }
}
