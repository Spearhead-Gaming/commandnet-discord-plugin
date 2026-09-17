<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use MajesticDev\Discord\Api\Processor\RegistrationProcessor;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Post('/discord/register-bot', processor: RegistrationProcessor::class),
    ]
)]
class DiscordRegistration
{
    #[ApiProperty(identifier: true)]
    public readonly int $id;

    #[Groups('DiscordRegistration')]
    public string $endpoint;

    #[Groups('DiscordRegistration')]
    public string $token;

    public function __construct()
    {
        $this->id = time();
    }
}
