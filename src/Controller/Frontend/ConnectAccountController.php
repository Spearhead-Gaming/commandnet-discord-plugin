<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Controller\Frontend;

use Forumify\Core\Entity\User;
use Forumify\OAuth\Idp\DiscordIdp;
use Forumify\OAuth\Repository\IdentityProviderRepository;
use Forumify\OAuth\Repository\IdentityProviderUserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('connect', 'connect')]
class ConnectAccountController extends AbstractController
{
    public function __construct(
        private readonly IdentityProviderRepository $idpRepository,
        private readonly IdentityProviderUserRepository $idpUserRepository,
    ) {
    }

    public function __invoke(): Response
    {
        $idps = $this->idpRepository->findBy(['type' => DiscordIdp::getType()], limit: 1);
        $discordIdp = reset($idps);
        if (!$discordIdp) {
            $this->addFlash('error', 'discord.connect.no_idp');
            return $this->redirectToRoute('forumify_core_index');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('forumify_core_login');
        }

        $userIdps = $this->idpUserRepository->findBy(['user' => $this->getUser()]);
        foreach ($userIdps as $uidp) {
            if ($uidp->getIdentityProvider()->getType() === DiscordIdp::getType()) {
                $this->addFlash('success', 'discord.connect.already_linked');
                return $this->redirectToRoute('forumify_core_index');
            }
        }

        return $this->render('@CommandNetDiscordPlugin/frontend/connect.html.twig', [
            'discordIdp' => $discordIdp,
        ]);
    }
}
