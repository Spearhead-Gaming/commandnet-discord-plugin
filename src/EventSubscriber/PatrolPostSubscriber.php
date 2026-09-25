<?php

declare(strict_types=1);

namespace MajesticDev\Discord\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use MajesticDev\CommandNet\Entity\Enum\OperationType;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Entity\OperationAAR;
use MajesticDev\CommandNet\Entity\OperationRSVP;
use MajesticDev\Discord\Service\PatrolPostService;

/**
 * Notices when a patrol, or something on it, changes and refreshes its Discord post - but only
 * once the flush has finished. The lifecycle events fire inside the flush's transaction, where
 * an exception would roll the patrol back and a nested flush (saving the message id) is not
 * allowed; postFlush is after the commit, so neither problem exists.
 *
 * The RSVP list, cancelling and filing an AAR all change what the post shows, so all of them
 * queue the patrol for a refresh.
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
#[AsDoctrineListener(event: Events::postFlush)]
class PatrolPostSubscriber
{
    /** @var array<int, bool> patrol id => is it new */
    private array $queue = [];

    private bool $syncing = false;

    public function __construct(private readonly PatrolPostService $postService)
    {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->queue($args->getObject(), true);
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->queue($args->getObject(), false);
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->queue($args->getObject(), false);
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->syncing || $this->queue === []) {
            return;
        }

        $queue = $this->queue;
        $this->queue = [];

        // Saving the message id flushes again, which lands back here with an empty queue.
        $this->syncing = true;
        try {
            foreach ($queue as $patrolId => $isNew) {
                $patrol = $args->getObjectManager()->find(Operation::class, $patrolId);
                if ($patrol instanceof Operation) {
                    $this->postService->sync($patrol, $isNew);
                }
            }
        } finally {
            $this->syncing = false;
        }
    }

    private function queue(object $entity, bool $created): void
    {
        $patrol = match (true) {
            $entity instanceof Operation => $entity,
            $entity instanceof OperationRSVP => $entity->getOperation(),
            $entity instanceof OperationAAR => $entity->getOperation(),
            default => null,
        };
        if ($patrol === null || $patrol->getType() !== OperationType::PATROL || $patrol->getId() === null) {
            return;
        }

        $isNew = $created && $entity instanceof Operation;
        $this->queue[$patrol->getId()] = ($this->queue[$patrol->getId()] ?? false) || $isNew;
    }
}
