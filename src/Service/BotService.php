<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Service;

use Forumify\Core\Entity\Role;
use Forumify\Core\Entity\User;
use Forumify\Core\Repository\SettingRepository;
use MajesticDev\Discord\Api\Resource\PostMessage;
use MajesticDev\Discord\Api\Resource\RolesChanged;
use MajesticDev\Discord\Api\Resource\UsernameChanged;
use MajesticDev\Discord\Entity\DiscordConnection;
use MajesticDev\Discord\Entity\DiscordRoleMapping;
use MajesticDev\Discord\Exception\DiscordBotException;
use MajesticDev\Discord\Exception\NoBotRegisteredException;
use MajesticDev\Discord\Repository\DiscordConnectionRepository;
use Forumify\OAuth\Entity\OAuthClient;
use Forumify\OAuth\Idp\DiscordIdp;
use Forumify\OAuth\Repository\IdentityProviderUserRepository;
use Forumify\OAuth\Repository\OAuthClientRepository;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * One bot process backs every Discord server (see DiscordConnection). This service still
 * talks to a single bot endpoint/token pair - what changed from upstream is that every
 * payload now carries which guild it applies to, and the caller never has to say which:
 * updateRoles()/updateUsername() figure that out themselves by walking every active
 * connection's own role mappings.
 */
class BotService
{
    public const string STATUS_ONLINE = 'online';
    public const string STATUS_OFFLINE = 'offline';
    public const string STATUS_NOT_REGISTERED = 'not-registered';

    private ?Client $client = null;

    public function __construct(
        private readonly SettingRepository $settingRepository,
        private readonly SerializerInterface $serializer,
        private readonly OAuthClientRepository $oAuthClientRepository,
        private readonly IdentityProviderUserRepository $idpUserRepository,
        private readonly DiscordConnectionRepository $connectionRepository,
    ) {
    }

    /**
     * @throws DiscordBotException
     */
    public function sendData(mixed $payload): void
    {
        $this->post($payload);
    }

    /**
     * For payloads the bot answers with data - a PostMessage answers with where the message
     * landed ({channelId, messageId}), which is what makes it editable later.
     *
     * @return array<string, mixed>
     *
     * @throws DiscordBotException
     */
    public function sendDataForResult(mixed $payload): array
    {
        try {
            $result = json_decode($this->post($payload), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $ex) {
            throw new DiscordBotException('The bot did not answer with data.', previous: $ex);
        }

        return is_array($result) ? $result : [];
    }

    /**
     * @throws DiscordBotException
     */
    private function post(mixed $payload): string
    {
        try {
            return $this->getClient()->post('/data', [
                // Without this the bot's JSON body parser skips the body and sees an empty
                // payload ("Unknown payload type undefined"). JSON-LD is valid JSON.
                'headers' => ['Content-Type' => 'application/json'],
                'body' => $this->serializer->serialize($payload, 'jsonld'),
            ])->getBody()->getContents();
        } catch (GuzzleException $ex) {
            throw new DiscordBotException('Unable to send data to bot.', previous: $ex);
        }
    }

    /**
     * @param array<string, mixed> $args
     * @return array<mixed>
     *
     * @throws DiscordBotException
     */
    public function fetchData(string $type, array $args = []): array
    {
        $args['type'] = $type;
        $qs = http_build_query($args);

        try {
            $body = $this->getClient()->get("/data?$qs")->getBody()->getContents();
            return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (GuzzleException | JsonException $ex) {
            throw new DiscordBotException('Unable to retrieve data from bot.', previous: $ex);
        }
    }

    /**
     * @return array<array{id: string, name: string}>
     */
    public function getGuildRoles(string $guildId): array
    {
        return $this->fetchData('roles', ['guildId' => $guildId]);
    }

    /**
     * The human members of a guild (bots left out), for the member import.
     *
     * @return array<array{id: string, username: string, displayName: string}>
     *
     * @throws DiscordBotException
     */
    public function getGuildMembers(string $guildId): array
    {
        /** @var array<array{id: string, username: string, displayName: string}> */
        return $this->fetchData('guildMembers', ['guildId' => $guildId]);
    }

    public function updateUsername(User $user): void
    {
        if (!$this->settingRepository->get('discord.force_matching_username')) {
            return;
        }

        $idpUsers = $this->idpUserRepository->findByUserAndIdpType($user, DiscordIdp::getType());
        if (empty($idpUsers)) {
            return;
        }

        foreach ($this->connectionRepository->findActive() as $connection) {
            foreach ($idpUsers as $idpUser) {
                $dto = new UsernameChanged();
                $dto->guildId = $connection->getGuildId();
                $dto->discordIdentifier = $idpUser->getExternalIdentifier();
                $dto->discordUsername = $idpUser->getExternalUsername();
                $dto->newUsername = $user->getDisplayName();
                $this->sendData($dto);
            }
        }
    }

    /**
     * @param array<Role> $added
     * @param array<Role> $removed
     */
    public function updateRoles(User $user, array $added, array $removed): void
    {
        $idpUsers = $this->idpUserRepository->findByUserAndIdpType($user, DiscordIdp::getType());
        if (empty($idpUsers)) {
            return;
        }

        foreach ($this->connectionRepository->findActive() as $connection) {
            $rolesToSync = $this->getRolesToSync($connection);
            if (empty($rolesToSync)) {
                continue;
            }

            $rolesAdded = $this->resolveSnowflakes($added, $rolesToSync);
            $rolesRemoved = $this->resolveSnowflakes($removed, $rolesToSync);
            if (empty($rolesAdded) && empty($rolesRemoved)) {
                continue;
            }

            foreach ($idpUsers as $idpUser) {
                $dto = new RolesChanged();
                $dto->guildId = $connection->getGuildId();
                $dto->discordIdentifier = $idpUser->getExternalIdentifier();
                $dto->rolesAdded = $rolesAdded;
                $dto->rolesRemoved = $rolesRemoved;
                $this->sendData($dto);
            }
        }
    }

    /**
     * @param array<Role> $roles
     * @param array<int, array<string>> $rolesToSync
     * @return array<string>
     */
    private function resolveSnowflakes(array $roles, array $rolesToSync): array
    {
        $snowflakes = [];
        foreach ($roles as $role) {
            foreach ($rolesToSync[$role->getId()] ?? [] as $snowflake) {
                $snowflakes[] = $snowflake;
            }
        }
        return $snowflakes;
    }

    /**
     * @return array<int, array<string>> [forumifyRoleId => [discordRoleSnowflake]]
     */
    private function getRolesToSync(DiscordConnection $connection): array
    {
        $roleMap = [];
        /** @var DiscordRoleMapping $mapping */
        foreach ($connection->getRoleMappings() as $mapping) {
            $role = $mapping->getForumifyRole();
            if ($role === null) {
                continue;
            }
            $roleMap[$role->getId()][] = $mapping->getDiscordRoleId();
        }
        return $roleMap;
    }

    /**
     * Posts to every active connection's announcements channel - the generic "tell every
     * server something happened" primitive other plugins (calendar cross-posting today,
     * id-card/server-manager/s3-tools later) build on instead of talking to the bot
     * themselves. Connections without an announcements channel configured are skipped.
     *
     * @param array<string, mixed>|null $embed
     */
    public function postAnnouncement(string $content, ?array $embed = null): void
    {
        foreach ($this->connectionRepository->findActive() as $connection) {
            $channelId = $connection->getAnnouncementsChannelId();
            if ($channelId === null) {
                continue;
            }

            $dto = new PostMessage();
            $dto->guildId = $connection->getGuildId();
            $dto->channelId = $channelId;
            $dto->content = $content;
            $dto->embed = $embed;
            $this->sendData($dto);
        }
    }

    public function healthCheck(): string
    {
        try {
            $this->getClient()->get('/ready');
        } catch (NoBotRegisteredException) {
            return self::STATUS_NOT_REGISTERED;
        } catch (GuzzleException) {
            return self::STATUS_OFFLINE;
        }

        return self::STATUS_ONLINE;
    }

    public function getOrCreateOAuthClient(): OAuthClient
    {
        $clientId = $this->settingRepository->get('discord.oauth_client_id');
        if ($clientId === null) {
            $clientId = $this->generateClientId();
            $this->settingRepository->set('discord.oauth_client_id', $clientId);
        }

        $client = $this->oAuthClientRepository->findOneBy(['clientId' => $clientId]);
        if ($client === null) {
            $client = new OAuthClient();
            $client->setName('Discord Bot');
            $client->setClientId($clientId);
            $this->oAuthClientRepository->save($client);
        }

        return $client;
    }

    private function generateClientId(): string
    {
        $i = 0;
        $desired = 'forumify-discord-bot';
        do {
            $clientId = $desired . ($i === 0 ? '' : "-$i");
            $client = $this->oAuthClientRepository->findOneBy(['clientId' => $clientId]);
            $i++;
        } while ($client !== null);

        return $clientId;
    }

    private function getClient(): Client
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $endpoint = $this->settingRepository->get('discord.endpoint');
        $token = $this->settingRepository->get('discord.token');
        if (empty($endpoint) || empty($token)) {
            throw new NoBotRegisteredException();
        }

        $this->client = new Client([
            'base_uri' => $endpoint,
            'headers' => [
                'Authorization' => "Bearer $token",
            ],
        ]);
        return $this->client;
    }
}
