<?php

declare(strict_types=1);

namespace Forumify\Discord\Form;

use Forumify\Core\Entity\Role;
use Forumify\Discord\Entity\DiscordRoleMapping;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * One row of a DiscordConnection's role mapping. Now entity-backed (was a raw settings
 * array upstream), and the Discord role is a pasted snowflake rather than a live-fetched
 * choice list, so the admin screen doesn't depend on the bot being online to edit mappings.
 *
 * @extends AbstractType<DiscordRoleMapping>
 */
class DiscordRoleMappingType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DiscordRoleMapping::class,
            'label' => false,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('forumifyRole', EntityType::class, [
                'class' => Role::class,
                'choice_label' => 'title',
                'query_builder' => fn ($repo) => $repo->createQueryBuilder('r')
                    ->where('r.system = false')
                    ->orderBy('r.position', 'DESC'),
                'label' => false,
            ])
            ->add('discordRoleId', TextType::class, [
                'label' => false,
                'help' => 'Discord role ID (right-click the role in Server Settings > Roles with Developer Mode enabled).',
            ])
        ;
    }
}
