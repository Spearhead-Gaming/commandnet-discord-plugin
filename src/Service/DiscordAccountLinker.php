<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Service;

use Forumify\Core\Entity\User;
use Forumify\Core\Repository\UserRepository;
use Forumify\OAuth\Entity\IdentityProviderUser;
use Forumify\OAuth\Idp\DiscordIdp;
use Forumify\OAuth\Repository\IdentityProviderRepository;
use Forumify\OAuth\Repository\IdentityProviderUserRepository;

/**
 * Attaches a Discord id to an existing forum account. If a placeholder account created by the
 * member import already holds that id, the placeholder is deleted first - but only when it is
 * provably untouched (no email, never active, no roles), so no real account is ever removed.
 */
class DiscordAccountLinker
{
    public function __construct(
        private readonly IdentityProviderRepository $idpRepository,
        private readonly IdentityProviderUserRepository $idpUserRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * @return array{linked: bool, message: string}
     */
    public function link(User $target, string $discordId, string $discordUsername, bool $apply): array
    {
        $idp = $this->idpRepository->findOneBy(['type' => DiscordIdp::getType()]);
        if ($idp === null) {
            return $this->result(false, 'The Discord identity provider is not configured.');
        }

        $holder = $this->idpUserRepository->findOneBy(['identityProvider' => $idp, 'externalIdentifier' => $discordId]);
        if ($holder !== null && $holder->getUser() === $target) {
            return $this->result(true, 'Already linked.');
        }

        $targetLink = $this->idpUserRepository->findOneBy(['identityProvider' => $idp, 'user' => $target]);
        if ($targetLink !== null) {
            return $this->result(false, "{$target->getUsername()} is already linked to another Discord account.");
        }

        $placeholder = $holder?->getUser();
        if ($placeholder !== null && !$this->isUntouched($placeholder)) {
            return $this->result(
                false,
                "Discord id $discordId belongs to {$placeholder->getUsername()}, which has activity; not touching it.",
            );
        }

        if ($apply) {
            if ($holder !== null && $placeholder !== null) {
                $this->idpUserRepository->remove($holder);
                $this->userRepository->remove($placeholder);
            }
            $this->idpUserRepository->save(new IdentityProviderUser($target, $idp, $discordId, $discordUsername));
        }

        return $this->result(true, $placeholder !== null
            ? "Linked, replacing placeholder {$placeholder->getUsername()}."
            : 'Linked.');
    }

    private function isUntouched(User $user): bool
    {
        return $user->getEmail() === null
            && $user->getLastActivity() === null
            && count($user->getRoleEntities()) === 0;
    }

    /**
     * @return array{linked: bool, message: string}
     */
    private function result(bool $linked, string $message): array
    {
        return ['linked' => $linked, 'message' => $message];
    }
}
