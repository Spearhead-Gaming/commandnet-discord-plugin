<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Controller\Admin;

use Forumify\Core\Repository\SettingRepository;
use MajesticDev\Discord\Form\SettingsType;
use MajesticDev\Discord\Messenger\SyncAllUsernamesMessage;
use MajesticDev\Discord\Service\BotService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('settings', 'settings')]
class SettingsController extends AbstractController
{
    public function __construct(
        private readonly SettingRepository $settingRepository,
        private readonly BotService $botService,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $client = null;
        $status = $this->botService->healthCheck();
        if ($status === BotService::STATUS_NOT_REGISTERED) {
            $client = $this->botService->getOrCreateOAuthClient();
        }

        $form = null;
        if ($status === BotService::STATUS_ONLINE) {
            $settingsData = $this->settingRepository->toFormData('discord');
            $form = $this->createForm(SettingsType::class, $settingsData);
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $this->handleSaveSettings($form);
            }
        }

        return $this->render('@CommandNetDiscordPlugin/admin/settings/settings.html.twig', [
            'client' => $client,
            'form' => $form,
            'status' => $status,
        ]);
    }

    /**
     * @param FormInterface<array<string, mixed>> $form
     */
    private function handleSaveSettings(FormInterface $form): void
    {
        $oldSyncUsername = $this->settingRepository->get('discord.force_matching_username');

        $settingsData = $form->getData();
        $this->settingRepository->handleFormData($settingsData);
        $this->addFlash('success', 'Discord settings saved.');

        $newSyncUsername = $this->settingRepository->get('discord.force_matching_username');

        if (!$oldSyncUsername && $newSyncUsername) {
            $this->messageBus->dispatch(new SyncAllUsernamesMessage());
        }
    }
}
