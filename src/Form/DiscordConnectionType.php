<?php

declare(strict_types=1);

namespace Forumify\Discord\Form;

use Forumify\Discord\Entity\DiscordConnection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<DiscordConnection>
 */
class DiscordConnectionType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DiscordConnection::class,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, [
                'help' => 'Internal name shown in the admin list, e.g. "1st Battalion" or "Community".',
            ])
            ->add('guildId', TextType::class, [
                'label' => 'Discord Server (Guild) ID',
                'help' => 'Enable Developer Mode in Discord (User Settings > Advanced), then right-click the server icon and Copy Server ID.',
            ])
            ->add('inviteLink', TextType::class, [
                'required' => false,
                'help' => 'Invite link for this server, set to never expire with no usage limit.',
            ])
            ->add('opsLogChannelId', TextType::class, [
                'label' => 'Ops Log Channel ID',
                'required' => false,
                'help' => 'Channel ID infra plugins (server manager, S3 tools, ...) post status updates to. Right-click the channel and Copy Channel ID.',
            ])
            ->add('announcementsChannelId', TextType::class, [
                'label' => 'Announcements Channel ID',
                'required' => false,
            ])
            ->add('active', CheckboxType::class, [
                'required' => false,
                'help' => 'Uncheck to stop syncing to this server without deleting the connection.',
            ])
            ->add('roleMappings', CollectionType::class, [
                'label' => 'Role Mapping',
                'entry_type' => DiscordRoleMappingType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false,
            ])
        ;
    }
}
