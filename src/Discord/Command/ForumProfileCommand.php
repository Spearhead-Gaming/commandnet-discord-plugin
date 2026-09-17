<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Discord\Command;

use Forumify\Core\Entity\User;
use Forumify\Core\Repository\UserRepository;
use Forumify\Core\Twig\Extension\CoreRuntime;
use MajesticDev\Discord\Api\DTO\DiscordCommandResult;
use MajesticDev\Discord\Api\DTO\DiscordCommandOption;
use MajesticDev\Discord\Api\DTO\DiscordEmbed;
use MajesticDev\Discord\Api\Resource\DiscordCommandRun;
use MajesticDev\Discord\Discord\DiscordCommandInterface;
use MajesticDev\Discord\Service\BadgeImageComposer;
use Forumify\Forum\Repository\CommentRepository;
use Forumify\Forum\Repository\SubscriptionRepository;
use Forumify\Forum\Repository\TopicRepository;
use Forumify\Forum\Service\UserReputationService;
use Forumify\OAuth\Idp\DiscordIdp;
use Forumify\OAuth\Repository\IdentityProviderUserRepository;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ForumProfileCommand implements DiscordCommandInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly Packages $packages,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly UrlHelper $urlHelper,
        private readonly TopicRepository $topicRepository,
        private readonly CommentRepository $commentRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly UserReputationService $userRepouitationService,
        private readonly CoreRuntime $coreRuntime,
        private readonly BadgeImageComposer $badgeImageComposer,
        private readonly IdentityProviderUserRepository $idpUserRepository,
    ) {
    }

    public function getName(): string
    {
        return 'forum-profile';
    }

    public function getDescription(): string
    {
        return 'Shows a forum member\'s profile.';
    }

    public function getOptions(): array
    {
        return [
            new DiscordCommandOption()
                ->setName('username')
                ->setDescription('Optional username, if left blank it will show your own profile.'),
        ];
    }

    public function run(DiscordCommandRun $command): DiscordCommandResult
    {
        $result = new DiscordCommandResult();

        $user = $this->getUserFromCmd($command);
        if ($user === null) {
            $result->content = "We could not find your forum profile :cry:. Try coupling your Discord account in your forum account settings, or log in to your forum account using Discord at least once.\n\nIf your forum does not support log in by Discord, provide the `username` option to the command.";
            return $result;
        }

        $url = $this->urlGenerator->generate('forumify_forum_profile', [
            'username' => $user->getUsername(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $embed = new DiscordEmbed(
            title: $user->getDisplayName(),
            url: $url,
            description: '@' . $user->getUsername(),
        );

        $this->addStatsFields($embed, $user);

        $avatar = $user->getAvatar();
        if ($avatar) {
            $avatarUrl = $this->packages->getUrl($avatar, 'forumify.avatar');
            $embed->setThumbnail($this->urlHelper->getAbsoluteUrl($avatarUrl));
        }

        $embed->setFooter(sprintf(
            "%s has been a member since %s, they were last seen %s.",
            $user->getDisplayName(),
            $this->coreRuntime->formatDate($user->getCreatedAt()),
            $user->getLastActivity() !== null
                ? strtolower($this->coreRuntime->formatDate($user->getLastActivity()))
                : 'never',
        ));

        $badges = $user->getBadges()->slice(0, 6);
        if (!empty($badges)) {
            $badgeComposite = $this->badgeImageComposer->compose($badges);
            $badgeUrl = $this->packages->getUrl($badgeComposite, 'forumify.asset');
            $embed->setImage($this->urlHelper->getAbsoluteUrl($badgeUrl));
        }

        $result->embeds[] = $embed;
        return $result;
    }

    private function addStatsFields(DiscordEmbed $embed, User $user): void
    {
        $topics = $this->topicRepository->count(['createdBy' => $user]);
        $comments = $this->commentRepository->count(['createdBy' => $user]);
        $followers = $this->subscriptionRepository->count([
            'type' => 'user_follow',
            'subjectId' => $user->getId(),
        ]);
        $following = $this->subscriptionRepository->count([
            'user' => $user,
            'type' => 'user_follow',
        ]);
        $reputation = $this->userRepouitationService->getReputation($user);

        $embed
            ->addField('', "**$topics** topics", true)
            ->addField('', "**$comments** comments", true)
            ->addField('', '', true)
            ->addField('', "**$followers** followers", true)
            ->addField('', "**$following** following", true)
            ->addField('', "**$reputation** reputation", true)
            ->addField()
        ;
    }

    private function getUserFromCmd(DiscordCommandRun $command): ?User
    {
        $username = $command->options['username'] ?? null;
        if ($username !== null) {
            return $this->userRepository->findOneBy(['username' => $username]);
        }

        return $this
            ->idpUserRepository
            ->findOneByExternalIdAndIdpType($command->discordUserId, DiscordIdp::getType())
            ?->getUser();
    }
}
