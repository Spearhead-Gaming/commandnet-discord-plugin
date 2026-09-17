<?php

declare(strict_types=1);

namespace Forumify\Discord\Controller\Frontend;

use Forumify\Discord\Repository\DiscordConnectionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * /discord/join keeps working as before, pointing at whichever connection was set up
 * first. /discord/join/{guildId} links to a specific unit's server invite instead.
 */
#[Route('join/{guildId}', 'join', defaults: ['guildId' => null])]
class JoinServerController extends AbstractController
{
    public function __construct(
        private readonly DiscordConnectionRepository $connectionRepository,
    ) {
    }

    public function __invoke(?string $guildId): Response
    {
        $connection = $guildId !== null
            ? $this->connectionRepository->findByGuildId($guildId)
            : ($this->connectionRepository->findActive()[0] ?? null);

        $inviteLink = $connection?->getInviteLink();
        if (is_string($inviteLink) && !str_starts_with($inviteLink, 'https://')) {
            $inviteLink = "https://discord.gg/$inviteLink";
        }

        return $this->render('@ForumifyDiscordPlugin/frontend/join.html.twig', [
            'inviteLink' => $inviteLink,
        ]);
    }
}
