<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Messenger;

use Doctrine\ORM\Mapping\ClassMetadata;
use MajesticDev\Discord\Exception\DiscordBotException;
use MajesticDev\Discord\Service\BotService;
use Forumify\OAuth\Idp\DiscordIdp;
use Forumify\OAuth\Repository\IdentityProviderUserRepository;
use Forumify\OAuth\Entity\IdentityProviderUser;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

#[AsMessageHandler(handles: SyncAllUsernamesMessage::class)]
class SyncAllUsernamesHandler
{
    public function __construct(
        private readonly IdentityProviderUserRepository $idpUserRepository,
        private readonly BotService $botService,
    ) {
    }

    public function __invoke(): void
    {
        if ($this->botService->healthCheck() !== BotService::STATUS_ONLINE) {
            throw new RecoverableMessageHandlingException(retryDelay: 300_000);
        }

        /** @var iterable<IdentityProviderUser> $discordUsers */
        $discordUsers = $this
            ->idpUserRepository
            ->createQueryBuilder('iu')
            ->innerJoin('iu.identityProvider', 'i')
            ->where('i.type = :type')
            ->setParameter('type', DiscordIdp::getType())
            ->getQuery()
            ->setFetchMode(IdentityProviderUser::class, 'user', ClassMetadata::FETCH_EAGER)
            ->toIterable()
        ;

        foreach ($discordUsers as $idpUser) {
            try {
                $this->botService->updateUsername($idpUser->getUser());
            } catch (DiscordBotException) {
                // ok
            }
        }
    }
}
