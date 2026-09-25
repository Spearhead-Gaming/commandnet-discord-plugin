<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Tests\Unit;

use Forumify\Core\Entity\User;
use Forumify\Core\Repository\UserRepository;
use Forumify\OAuth\Entity\IdentityProvider;
use Forumify\OAuth\Entity\IdentityProviderUser;
use Forumify\OAuth\Repository\IdentityProviderRepository;
use Forumify\OAuth\Repository\IdentityProviderUserRepository;
use MajesticDev\Discord\Entity\DiscordConnection;
use MajesticDev\Discord\Repository\DiscordConnectionRepository;
use MajesticDev\Discord\Service\BotService;
use MajesticDev\Discord\Service\DiscordAccountLinker;
use MajesticDev\Discord\Service\DiscordMemberImporter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class DiscordMemberImporterTest extends TestCase
{
    /** @var list<object> */
    private array $saved = [];

    /** @var list<string> */
    private array $linked = [];

    /**
     * @param array<string, string> $linkedIds Discord ids that already have a linked account
     * @param array<string> $forumUsernames
     */
    private function importer(array $members, array $linkedIds = [], array $forumUsernames = []): DiscordMemberImporter
    {
        $this->saved = [];

        $bot = $this->createStub(BotService::class);
        $bot->method('getGuildMembers')->willReturn($members);

        $connection = $this->createStub(DiscordConnection::class);
        $connection->method('getGuildId')->willReturn('g1');
        $connections = $this->createStub(DiscordConnectionRepository::class);
        $connections->method('findActive')->willReturn([$connection]);

        $idps = $this->createStub(IdentityProviderRepository::class);
        $idps->method('findOneBy')->willReturn($this->createStub(IdentityProvider::class));

        $idpUsers = $this->createStub(IdentityProviderUserRepository::class);
        $idpUsers->method('findOneBy')->willReturnCallback(
            fn (array $c) => isset($linkedIds[$c['externalIdentifier']])
                ? $this->createStub(IdentityProviderUser::class)
                : null,
        );
        $idpUsers->method('save')->willReturnCallback(function (object $e): void {
            $this->saved[] = $e;
        });

        $users = $this->createStub(UserRepository::class);
        $users->method('findOneBy')->willReturnCallback(
            fn (array $c) => in_array($c['username'], $forumUsernames, true) ? new User() : null,
        );

        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hash');

        $this->linked = [];
        $linker = $this->createStub(DiscordAccountLinker::class);
        $linker->method('link')->willReturnCallback(function (User $u, string $id) {
            $this->linked[] = $id;
            return ['linked' => true, 'message' => 'Linked.'];
        });

        return new DiscordMemberImporter($bot, $connections, $idps, $idpUsers, $users, $hasher, $linker);
    }

    public function testMatchLinksExactUsernameMatchesInsteadOfSkipping(): void
    {
        $result = $this->importer([$this->member('1', 'doeboy', 'Cpl Doe')], [], ['doeboy'])->import(true, true);

        self::assertSame(['1' => 'doeboy'], $result['matched']);
        self::assertSame(['1'], $this->linked);
        self::assertSame([], $result['created']);
    }

    public function testMatchNeverUsesTheDisplayName(): void
    {
        $result = $this->importer([$this->member('1', 'someoneelse', 'doeboy')], [], ['doeboy'])->import(true, true);

        self::assertSame([], $result['matched']);
        self::assertSame(['1' => 'someoneelse'], $result['possibleMatches']);
    }

    private function member(string $id, string $username, string $displayName = ''): array
    {
        return ['id' => $id, 'username' => $username, 'displayName' => $displayName ?: $username];
    }

    public function testDryRunReportsButWritesNothing(): void
    {
        $result = $this->importer([$this->member('1', 'doeboy')])->import(false);

        self::assertSame(['1' => 'doeboy'], $result['created']);
        self::assertSame([], $this->saved);
    }

    public function testApplyCreatesAndLinksAccounts(): void
    {
        $result = $this->importer([$this->member('1', 'doeboy')])->import(true);

        self::assertSame(['1' => 'doeboy'], $result['created']);
        self::assertCount(1, $this->saved);
        self::assertInstanceOf(IdentityProviderUser::class, $this->saved[0]);
        self::assertSame('1', $this->saved[0]->getExternalIdentifier());
    }

    public function testAlreadyLinkedMembersAreCountedNotRecreated(): void
    {
        $result = $this->importer([$this->member('1', 'doeboy')], ['1' => true])->import(true);

        self::assertSame(1, $result['linked']);
        self::assertSame([], $result['created']);
    }

    public function testNameMatchingAForumAccountIsSkippedAndReported(): void
    {
        $result = $this->importer([$this->member('1', 'doeboy', 'Cpl Doe'), $this->member('2', 'roeboy')], [], ['doeboy'])->import(true);

        self::assertSame(['1' => 'doeboy'], $result['possibleMatches']);
        self::assertSame(['2' => 'roeboy'], $result['created']);
    }
}
