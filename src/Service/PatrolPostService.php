<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Service;

use MajesticDev\CommandNet\Entity\Enum\OperationStatus;
use MajesticDev\CommandNet\Entity\Enum\RsvpStatus;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Entity\OperationRSVP;
use MajesticDev\Discord\Api\Resource\DeleteMessage;
use MajesticDev\Discord\Api\Resource\EditMessage;
use MajesticDev\Discord\Api\Resource\PostMessage;
use MajesticDev\Discord\Entity\DiscordConnection;
use MajesticDev\Discord\Entity\PatrolMessage;
use MajesticDev\Discord\Exception\DiscordBotException;
use MajesticDev\Discord\Repository\DiscordConnectionRepository;
use MajesticDev\Discord\Repository\PatrolMessageRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Keeps a patrol's post in each Discord server's patrols channel up to date: posts it when the
 * patrol is created, edits it as people join or leave, and takes the buttons off once the
 * patrol is cancelled or its AAR is filed. A server with no patrols channel gets the old plain
 * announcement instead, once, when the patrol is created.
 *
 * Nothing here may throw: this runs after a patrol is saved, and Discord being unreachable
 * must never cost anyone their patrol. Failures are logged and the next change retries.
 */
class PatrolPostService
{
    public function __construct(
        private readonly BotService $botService,
        private readonly DiscordConnectionRepository $connectionRepository,
        private readonly PatrolMessageRepository $messageRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function sync(Operation $patrol, bool $isNew): void
    {
        foreach ($this->connectionRepository->findActive() as $connection) {
            try {
                $this->syncConnection($patrol, $connection, $isNew);
            } catch (DiscordBotException $ex) {
                $this->logger->error('Could not update the patrol post in Discord.', [
                    'exception' => $ex,
                    'patrol' => $patrol->getId(),
                    'guild' => $connection->getGuildId(),
                ]);
            }
        }
    }

    /**
     * Deletes a patrol's posts from every server, and forgets them. Called once the patrol is
     * really gone. A post that cannot be deleted (the bot is down, or lost its permission) is
     * logged and left for a person to remove - the record is dropped either way, since there is
     * no patrol left for it to be refreshed against.
     */
    public function removePosts(int $patrolId): void
    {
        foreach ($this->messageRepository->findAllForPatrol($patrolId) as $message) {
            $delete = new DeleteMessage();
            $delete->guildId = $message->getGuildId();
            $delete->channelId = $message->getChannelId();
            $delete->messageId = $message->getMessageId();

            try {
                $this->botService->sendData($delete);
            } catch (DiscordBotException $ex) {
                $this->logger->error('Could not delete the patrol post from Discord.', [
                    'exception' => $ex,
                    'patrol' => $patrolId,
                    'guild' => $message->getGuildId(),
                    'message' => $message->getMessageId(),
                ]);
            }

            $this->messageRepository->remove($message);
        }
    }

    private function syncConnection(Operation $patrol, DiscordConnection $connection, bool $isNew): void
    {
        $patrolId = (int)$patrol->getId();
        $state = $this->stateOf($patrol);
        $open = $state === PatrolPostFormatter::OPEN;
        $embed = $this->embedFor($patrol, $state);

        $existing = $this->messageRepository->findForPatrol($patrolId, $connection->getGuildId());
        if ($existing !== null) {
            $edit = new EditMessage();
            $edit->guildId = $connection->getGuildId();
            $edit->channelId = $existing->getChannelId();
            $edit->messageId = $existing->getMessageId();
            $edit->embed = $embed;
            $edit->components = $open ? PatrolPostFormatter::components($patrolId) : [];
            $this->botService->sendData($edit);
            return;
        }

        if (!$isNew || !$open) {
            return;
        }

        $channelId = $connection->getPatrolsChannelId();
        if ($channelId === null) {
            $this->announce($connection, $patrol);
            return;
        }

        $post = new PostMessage();
        $post->guildId = $connection->getGuildId();
        $post->channelId = $channelId;
        $post->embed = $embed;
        $post->components = PatrolPostFormatter::components($patrolId);
        $result = $this->botService->sendDataForResult($post);

        if (isset($result['messageId'], $result['channelId'])) {
            $this->messageRepository->save(new PatrolMessage(
                $patrolId,
                $connection->getGuildId(),
                (string)$result['channelId'],
                (string)$result['messageId'],
            ));
        }
    }

    /**
     * The plain text announcement, for servers that have not set a patrols channel.
     */
    private function announce(DiscordConnection $connection, Operation $patrol): void
    {
        $channelId = $connection->getAnnouncementsChannelId();
        if ($channelId === null) {
            return;
        }

        $leader = $patrol->getLeader();
        $post = new PostMessage();
        $post->guildId = $connection->getGuildId();
        $post->channelId = $channelId;
        $post->content = sprintf(
            "**New patrol: %s**\n%s%s%s",
            $patrol->getTitle(),
            $patrol->getStartDateTime()->format('l, F j, Y \a\t g:i A'),
            $patrol->getLocation() !== null ? ' - ' . $patrol->getLocation() : '',
            $leader !== null ? "\nLed by " . $leader->getDisplayName() : '',
        );
        $this->botService->sendData($post);
    }

    private function stateOf(Operation $patrol): string
    {
        if ($patrol->getStatus() === OperationStatus::CANCELLED) {
            return PatrolPostFormatter::CANCELLED;
        }

        $done = $patrol->getStatus() === OperationStatus::COMPLETED || count($patrol->getAars()) > 0;

        return $done ? PatrolPostFormatter::COMPLETED : PatrolPostFormatter::OPEN;
    }

    /**
     * @return array<string, mixed>
     */
    private function embedFor(Operation $patrol, string $state): array
    {
        $joined = [];
        /** @var OperationRSVP $rsvp */
        foreach ($patrol->getRsvps() as $rsvp) {
            if ($rsvp->getStatus() === RsvpStatus::ATTENDING) {
                $joined[] = $rsvp->getSoldier()->getUser()->getDisplayName();
            }
        }
        sort($joined, SORT_NATURAL | SORT_FLAG_CASE);

        return PatrolPostFormatter::embed(
            (int)$patrol->getId(),
            $patrol->getTitle(),
            $this->urlGenerator->generate(
                'command_net_operation_detail',
                ['id' => $patrol->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
            $patrol->getStartDateTime(),
            $patrol->getLocation(),
            $patrol->getLeader()?->getDisplayName(),
            $patrol->getContent(),
            $joined,
            $patrol->getMaxParticipants(),
            $state,
        );
    }
}
