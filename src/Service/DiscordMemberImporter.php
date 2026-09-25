<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Service;

use Forumify\Core\Entity\User;
use Forumify\Core\Repository\UserRepository;
use Forumify\OAuth\Entity\IdentityProviderUser;
use Forumify\OAuth\Idp\DiscordIdp;
use Forumify\OAuth\Repository\IdentityProviderRepository;
use Forumify\OAuth\Repository\IdentityProviderUserRepository;
use MajesticDev\Discord\Exception\DiscordBotException;
use MajesticDev\Discord\Repository\DiscordConnectionRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Creates a forumify account, linked to its Discord id, for every human member of every active
 * guild that has none yet - so members get the built-in "user" role's permissions without ever
 * logging in. When one of them does log in with Discord, forumify finds the linked account by
 * Discord id and reuses it.
 *
 * A member whose Discord username or display name matches an existing forum username is skipped
 * and reported instead: linking a placeholder next to a real account would leave that person
 * with two accounts once they connect Discord. With $match, an exact Discord *username* match
 * (never the display name, which anyone can set to anything) is linked to that forum account.
 */
class DiscordMemberImporter
{
    public function __construct(
        private readonly BotService $botService,
        private readonly DiscordConnectionRepository $connectionRepository,
        private readonly IdentityProviderRepository $idpRepository,
        private readonly IdentityProviderUserRepository $idpUserRepository,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly DiscordAccountLinker $linker,
    ) {
    }

    /**
     * created/matched/possibleMatches map Discord id => Discord username.
     *
     * @return array{
     *     created: array<string, string>,
     *     matched: array<string, string>,
     *     linked: int,
     *     possibleMatches: array<string, string>,
     *     errors: array<string>
     * }
     */
    public function import(bool $apply, bool $match = false): array
    {
        $result = ['created' => [], 'matched' => [], 'linked' => 0, 'possibleMatches' => [], 'errors' => []];

        $idp = $this->idpRepository->findOneBy(['type' => DiscordIdp::getType()]);
        if ($idp === null) {
            $result['errors'][] = 'The Discord identity provider is not configured.';
            return $result;
        }

        $members = [];
        foreach ($this->connectionRepository->findActive() as $connection) {
            try {
                foreach ($this->botService->getGuildMembers($connection->getGuildId()) as $member) {
                    $members[$member['id']] = $member;
                }
            } catch (DiscordBotException $ex) {
                $result['errors'][] = "Guild {$connection->getGuildId()}: {$ex->getMessage()}";
            }
        }

        foreach ($members as $member) {
            // numeric snowflakes turn into int array keys, so the id is taken from the member itself
            $id = (string)$member['id'];

            if ($match) {
                $forumUser = $this->userRepository->findOneBy(['username' => $this->toUsername($member['username'])]);
                if ($forumUser !== null) {
                    $linked = $this->linker->link($forumUser, $id, $member['username'], $apply);
                    if ($linked['linked']) {
                        $result['matched'][$id] = $member['username'];
                        continue;
                    }
                    $result['errors'][] = "{$member['username']}: {$linked['message']}";
                }
            }

            $existing = ['identityProvider' => $idp, 'externalIdentifier' => $id];
            if ($this->idpUserRepository->findOneBy($existing) !== null) {
                ++$result['linked'];
                continue;
            }

            if ($this->matchesExistingUser($member)) {
                $result['possibleMatches'][$id] = $member['username'];
                continue;
            }

            $result['created'][$id] = $member['username'];
            if (!$apply) {
                continue;
            }

            $user = $this->createUser($member);
            $this->idpUserRepository->save(new IdentityProviderUser($user, $idp, $id, $member['username']));
        }

        return $result;
    }

    /**
     * @param array{id: string, username: string, displayName: string} $member
     */
    private function matchesExistingUser(array $member): bool
    {
        foreach ([$member['username'], $member['displayName']] as $name) {
            if ($this->userRepository->findOneBy(['username' => $this->toUsername($name)]) !== null) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array{id: string, username: string, displayName: string} $member
     */
    private function createUser(array $member): User
    {
        $base = $this->toUsername($member['username']);
        $username = $base;
        for ($i = 1; $this->userRepository->findOneBy(['username' => $username]) !== null; ++$i) {
            $username = substr($base, 0, 32 - strlen((string)$i)) . $i;
        }

        $user = new User();
        $user->setUsername($username);
        $user->setDisplayName($member['displayName'] !== '' ? $member['displayName'] : $username);
        $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(24))));
        $user->setEmailVerified(true);
        $this->userRepository->save($user);

        return $user;
    }

    /**
     * Forumify usernames are 4-32 characters of [A-Za-z0-9-_] with at least one letter.
     */
    private function toUsername(string $name): string
    {
        $username = substr(preg_replace('/[^A-Za-z0-9_-]/', '', $name) ?? '', 0, 32);
        if (!preg_match('/[A-Za-z]/', $username)) {
            $username = 'member' . $username;
        }
        return str_pad($username, 4, '_');
    }
}
