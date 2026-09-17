<?php

declare(strict_types=1);

namespace Forumify\Discord\Admin\Component;

use Forumify\Core\Component\Table\AbstractDoctrineTable;
use Forumify\Discord\Entity\DiscordConnection;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;

#[AsLiveComponent('Forumify\\DiscordConnectionTable', '@Forumify/components/table/table.html.twig')]
#[IsGranted('discord.admin.connections.view')]
class DiscordConnectionTable extends AbstractDoctrineTable
{
    protected function getEntityClass(): string
    {
        return DiscordConnection::class;
    }

    protected function buildTable(): void
    {
        $this
            ->addColumn('label', [
                'field' => 'label',
            ])
            ->addColumn('guildId', [
                'label' => 'Guild ID',
                'field' => 'guildId',
            ])
            ->addColumn('active', [
                'field' => 'active',
                'renderer' => fn (bool $active) => $active ? 'Active' : 'Disabled',
            ])
            ->addColumn('actions', [
                'field' => 'id',
                'label' => '',
                'renderer' => $this->renderActions(...),
                'searchable' => false,
                'sortable' => false,
            ])
        ;
    }

    private function renderActions(int $id): string
    {
        if (!$this->security->isGranted('discord.admin.connections.manage')) {
            return '';
        }

        $actions = '';
        $actions .= $this->renderAction('forumify_admin_discord_connections_edit', ['identifier' => $id], 'pencil-simple-line');
        $actions .= $this->renderAction('forumify_admin_discord_connections_delete', ['identifier' => $id], 'x');
        return $actions;
    }
}
