<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Forumify\Core\Repository\SettingRepository;
use MajesticDev\Discord\Api\DTO\DiscordCommandResult;
use MajesticDev\Discord\Api\DTO\DiscordEmbed;
use MajesticDev\Discord\Api\Resource\DiscordCommandRun;
use MajesticDev\Discord\Discord\DiscordCommandInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @implements ProcessorInterface<DiscordCommandRun, DiscordCommandResult>
 */
class DiscordCommandRunProcessor implements ProcessorInterface
{
    /**
     * @param iterable<DiscordCommandInterface> $commands
     */
    public function __construct(
        #[AutowireIterator('discord.command')]
        private readonly iterable $commands,
        private readonly SettingRepository $settingRepository,
        private readonly Packages $packages,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly UrlHelper $urlHelper,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $command = $this->getCommand($data->name);
        $result = $command->run($data);

        foreach ($result->embeds as $embed) {
            $this->decorateEmbed($embed);
        }

        return $result;
    }

    private function getCommand(string $name): DiscordCommandInterface
    {
        foreach ($this->commands as $command) {
            if ($command->getName() === $name) {
                return $command;
            }
        }
        throw new NotFoundHttpException("Command \"$name\" does not exist.");
    }

    private function decorateEmbed(DiscordEmbed $embed): void
    {
        $logo = $this->settingRepository->get('forumify.logo');
        if ($logo) {
            $logo = $this->packages->getUrl($logo, 'forumify.asset');
        } else {
            $logo = $this->packages->getUrl('bundles/forumify/images/forumify.svg');
        }

        $embed->author = [
            'name' => $this->settingRepository->get('forumify.title') ?? 'A forum',
            'icon_url' => $this->urlHelper->getAbsoluteUrl($logo),
            'url' => $this->urlGenerator->generate('forumify_core_index', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ];
    }
}
