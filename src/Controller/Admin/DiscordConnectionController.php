<?php

declare(strict_types=1);

namespace Forumify\Discord\Controller\Admin;

use Forumify\Admin\Crud\AbstractCrudController;
use Forumify\Discord\Entity\DiscordConnection;
use Forumify\Discord\Form\DiscordConnectionType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @extends AbstractCrudController<DiscordConnection>
 */
#[Route('/discord/connections', 'discord_connections')]
class DiscordConnectionController extends AbstractCrudController
{
    protected ?string $permissionView = 'discord.admin.connections.view';
    protected ?string $permissionCreate = 'discord.admin.connections.manage';
    protected ?string $permissionEdit = 'discord.admin.connections.manage';
    protected ?string $permissionDelete = 'discord.admin.connections.manage';

    protected function getEntityClass(): string
    {
        return DiscordConnection::class;
    }

    protected function getTableName(): string
    {
        return 'Forumify\\DiscordConnectionTable';
    }

    protected function getForm(?object $data): FormInterface
    {
        return $this->createForm(DiscordConnectionType::class, $data);
    }
}
