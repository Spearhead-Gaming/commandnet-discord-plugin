<?php

declare(strict_types=1);

namespace Tests\Unit;

use DateTime;
use Forumify\Core\Entity\Role;
use Forumify\Core\Entity\User;
use Forumify\Core\Repository\UserRepository;
use Forumify\OAuth\Entity\IdentityProvider;
use Forumify\OAuth\Entity\IdentityProviderUser;
use Forumify\OAuth\Repository\IdentityProviderRepository;
use Forumify\OAuth\Repository\IdentityProviderUserRepository;
use MajesticDev\Discord\Service\DiscordAccountLinker;
use PHPUnit\Framework\TestCase;

class DiscordAccountLinkerTest extends TestCase
{
    /** @var list<object> */
    private array $removed = [];

    /** @var list<object> */
    private array $saved = [];

    private function user(string $name, ?string $email = null): User
    {
        $user = new User();
        $user->setUsername($name);
        $user->setEmail($email);
        return $user;
    }

    /**
     * @param IdentityProviderUser|null $holder who currently holds the Discord id
     * @param IdentityProviderUser|null $targetLink the target's existing Discord link
     */
    private function linker(?IdentityProviderUser $holder, ?IdentityProviderUser $targetLink = null): DiscordAccountLinker
    {
        $this->removed = $this->saved = [];

        $idps = $this->createStub(IdentityProviderRepository::class);
        $idps->method('findOneBy')->willReturn($this->createStub(IdentityProvider::class));

        $idpUsers = $this->createStub(IdentityProviderUserRepository::class);
        $idpUsers->method('findOneBy')->willReturnCallback(
            fn (array $c) => isset($c['externalIdentifier']) ? $holder : $targetLink,
        );
        $idpUsers->method('remove')->willReturnCallback(function (object $e): void {
            $this->removed[] = $e;
        });
        $idpUsers->method('save')->willReturnCallback(function (object $e): void {
            $this->saved[] = $e;
        });

        $users = $this->createStub(UserRepository::class);
        $users->method('remove')->willReturnCallback(function (object $e): void {
            $this->removed[] = $e;
        });

        return new DiscordAccountLinker($idps, $idpUsers, $users);
    }

    private function holding(User $user): IdentityProviderUser
    {
        return new IdentityProviderUser($user, $this->createStub(IdentityProvider::class), '1', 'doe');
    }

    public function testLinksWhenNobodyHoldsTheId(): void
    {
        $result = $this->linker(null)->link($this->user('realdoe', 'a@b.c'), '1', 'doe', true);

        self::assertTrue($result['linked']);
        self::assertCount(1, $this->saved);
        self::assertSame([], $this->removed);
    }

    public function testReplacesAnUntouchedPlaceholder(): void
    {
        $placeholder = $this->user('doe_');
        $holder = $this->holding($placeholder);

        $result = $this->linker($holder)->link($this->user('realdoe', 'a@b.c'), '1', 'doe', true);

        self::assertTrue($result['linked']);
        self::assertSame([$holder, $placeholder], $this->removed);
        self::assertCount(1, $this->saved);
    }

    public function testDryRunWritesNothing(): void
    {
        $result = $this->linker($this->holding($this->user('doe_')))->link($this->user('realdoe'), '1', 'doe', false);

        self::assertTrue($result['linked']);
        self::assertSame([], $this->removed);
        self::assertSame([], $this->saved);
    }

    public function testRefusesAPlaceholderThatHasBeenUsed(): void
    {
        $used = $this->user('doe_');
        $used->setLastActivity(new DateTime());

        $result = $this->linker($this->holding($used))->link($this->user('realdoe'), '1', 'doe', true);

        self::assertFalse($result['linked']);
        self::assertSame([], $this->removed);
    }

    public function testRefusesAHolderWithRolesOrEmail(): void
    {
        $withRole = $this->user('doe_');
        $withRole->addRoleEntity($this->createStub(Role::class));
        self::assertFalse($this->linker($this->holding($withRole))->link($this->user('a1'), '1', 'doe', true)['linked']);

        $withEmail = $this->user('doe_', 'x@y.z');
        self::assertFalse($this->linker($this->holding($withEmail))->link($this->user('a1'), '1', 'doe', true)['linked']);
    }

    public function testRefusesWhenTheTargetIsAlreadyLinkedToAnotherDiscordAccount(): void
    {
        $target = $this->user('realdoe');
        $result = $this->linker(null, $this->holding($target))->link($target, '2', 'doe', true);

        self::assertFalse($result['linked']);
        self::assertSame([], $this->saved);
    }
}
