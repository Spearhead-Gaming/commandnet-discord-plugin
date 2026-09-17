<?php

declare(strict_types=1);

namespace MajesticDev\Discord\EventSubscriber;

use DateInterval;
use Forumify\Core\Entity\User;
use Forumify\Core\Repository\SettingRepository;
use MajesticDev\Discord\Exception\DiscordBotException;
use MajesticDev\Discord\Service\BotService;
use Forumify\OAuth\Entity\IdentityProvider;
use Forumify\OAuth\Entity\IdentityProviderUser;
use Forumify\OAuth\Idp\DiscordIdp;
use Forumify\OAuth\Repository\IdentityProviderRepository;
use Forumify\OAuth\Repository\IdentityProviderUserRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[AsEventListener(event: KernelEvents::REQUEST, priority: -999)]
class ForceDiscordLoginListener
{
    public function __construct(
        private readonly SettingRepository $settingRepository,
        private readonly IdentityProviderRepository $idpRepository,
        private readonly IdentityProviderUserRepository $idpUserRepository,
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly BotService $botService,
        private readonly CacheInterface $cache,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $requestRoute = $event->getRequest()->attributes->get('_route');
        if ($requestRoute === null
            || $requestRoute === 'ux_live_component'
            || str_starts_with($requestRoute, 'discord_')
            || str_starts_with($requestRoute, 'forumify_admin_')
            || str_starts_with($requestRoute, 'forumify_core_')
            || str_starts_with($requestRoute, 'forumify_oauth_idp_')
        ) {
            return;
        }

        $forceConnect = $this->settingRepository->get('discord.force_connect_account');
        $forceInServer = $this->settingRepository->get('discord.force_user_in_server');
        if (!$forceConnect && !$forceInServer) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        /** @var array<IdentityProvider> $discordIdps */
        $discordIdps = $this->idpRepository->findBy(['type' => DiscordIdp::getType()]);
        if (empty($discordIdps)) {
            return;
        }

        /** @var array<IdentityProviderUser> $idpUsers */
        $idpUsers = $this->idpUserRepository->findBy(['user' => $user, 'identityProvider' => $discordIdps]);

        $response = null;
        if ($forceConnect) {
            $response = $this->forceConnect($idpUsers);
        }

        if ($forceInServer) {
            $response ??= $this->forceInServer($user, $idpUsers);
        }

        if ($response !== null) {
            $event->setResponse($response);
        }
    }

    /**
     * @param array<IdentityProviderUser> $idpUsers
     */
    private function forceConnect(array $idpUsers): ?Response
    {
        if (!empty($idpUsers)) {
            return null;
        }

        $connectRoute = $this->urlGenerator->generate('discord_connect');
        return new RedirectResponse($connectRoute);
    }

    /**
     * @param array<IdentityProviderUser> $idpUsers
     */
    private function forceInServer(User $user, array $idpUsers): ?Response
    {
        if (empty($idpUsers)) {
            return null;
        }

        $cacheKey = 'discord.user.' . $user->getId();
        $isInServer = $this->cache->get($cacheKey, function (ItemInterface $item) use ($idpUsers) {
            $inServer = $this->isInServer($idpUsers);
            $item->expiresAfter(new DateInterval($inServer ? 'P1D' : 'PT1S'));
            return $inServer;
        });

        if ($isInServer) {
            return null;
        }

        $joinRoute = $this->urlGenerator->generate('discord_join');
        return new RedirectResponse($joinRoute);
    }

    /**
     * @param array<IdentityProviderUser> $idpUsers
     */
    private function isInServer(array $idpUsers): bool
    {
        foreach ($idpUsers as $idpUser) {
            try {
                $result = $this->botService->fetchData('members', ['id' => $idpUser->getExternalIdentifier()]);
            } catch (DiscordBotException) {
                // If an exception happens, we just assume the user is in the server to prevent deadlocks
                return true;
            }

            if (!empty($result['userId'])) {
                return true;
            }
        }
        return false;
    }
}
