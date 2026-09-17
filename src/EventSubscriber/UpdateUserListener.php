<?php

declare(strict_types=1);

namespace MajesticDev\Discord\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Forumify\Core\Entity\Role;
use Forumify\Core\Entity\User;
use MajesticDev\Discord\Service\BotService;

#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
class UpdateUserListener
{
    /** @var array<User> */
    private array $usernameChanged = [];

    /** @var array<array{
     *   user: User,
     *   added: array<Role>,
     *   removed: array<Role>,
     * }>
     */
    private array $rolesChanged = [];

    public function __construct(
        private readonly BotService $botService,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        /** @var EntityManagerInterface $em */
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof User) {
                continue;
            }

            $changeset = $uow->getEntityChangeSet($entity);
            if (isset($changeset['displayName']) || isset($changeset['username'])) {
                $this->usernameChanged[] = $entity;
            }
        }

        foreach ($uow->getScheduledCollectionUpdates() as $collection) {
            if ($collection->getTypeClass()->name !== Role::class) {
                continue;
            }

            $owner = $collection->getOwner();
            if (!$owner instanceof User) {
                continue;
            }

            $this->rolesChanged[] = [
                'user' => $owner,
                'added' => $collection->getInsertDiff(),
                'removed' => $collection->getDeleteDiff(),
            ];
        }
    }

    public function postFlush(): void
    {
        foreach ($this->usernameChanged as $user) {
            $this->botService->updateUsername($user);
        }

        foreach ($this->rolesChanged as $roleChanged) {
            $this->botService->updateRoles(...$roleChanged);
        }
    }
}
