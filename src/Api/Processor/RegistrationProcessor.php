<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Forumify\Core\Repository\SettingRepository;
use MajesticDev\Discord\Api\Resource\DiscordRegistration;

/**
 * @implements ProcessorInterface<DiscordRegistration, void>
 */
class RegistrationProcessor implements ProcessorInterface
{
    public function __construct(private readonly SettingRepository $settingRepository)
    {
    }

    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ) {
        $this->settingRepository->setBulk([
            'discord.endpoint' => $data->endpoint,
            'discord.token' => $data->token,
        ]);
    }
}
