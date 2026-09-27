<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Tests\Unit\EventSubscriber;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use MajesticDev\CommandNet\Entity\Enum\OperationType;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\Discord\EventSubscriber\PatrolPostSubscriber;
use MajesticDev\Discord\Service\PatrolPostService;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class PatrolPostSubscriberTest extends TestCase
{
    protected function setUp(): void
    {
        // commandnet-plugin is an optional, un-required dependency (see phpstan.neon).
        if (!class_exists(Operation::class)) {
            $this->markTestSkipped('commandnet-plugin is not installed.');
        }
    }

    private function patrol(OperationType $type = OperationType::PATROL): Operation
    {
        $patrol = new Operation();
        (new ReflectionProperty($patrol, 'id'))->setValue($patrol, 12);
        $patrol->setType($type);
        $patrol->setTitle('Night patrol');
        $patrol->setStartDateTime(new DateTime('2026-09-26 20:00'));
        return $patrol;
    }

    /**
     * @param Operation|null $stillThere what the database still holds for the patrol after the flush
     */
    private function flush(PatrolPostSubscriber $subscriber, ?Operation $stillThere): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('find')->willReturn($stillThere);
        $subscriber->postFlush(new PostFlushEventArgs($em));
    }

    public function testADeletedPatrolHasItsPostsDeleted(): void
    {
        $service = $this->createMock(PatrolPostService::class);
        $service->expects(self::once())->method('removePosts')->with(12);
        $service->expects(self::never())->method('sync');
        $subscriber = new PatrolPostSubscriber($service);

        $patrol = $this->patrol();
        $subscriber->preRemove(new PreRemoveEventArgs($patrol, $this->createStub(EntityManagerInterface::class)));
        $this->flush($subscriber, null);
    }

    public function testAPatrolThatSurvivesTheFlushKeepsItsPosts(): void
    {
        $service = $this->createMock(PatrolPostService::class);
        $service->expects(self::never())->method('removePosts');
        $subscriber = new PatrolPostSubscriber($service);

        $patrol = $this->patrol();
        $subscriber->preRemove(new PreRemoveEventArgs($patrol, $this->createStub(EntityManagerInterface::class)));
        $this->flush($subscriber, $patrol);
    }

    public function testOtherEventsAreNotTouched(): void
    {
        $service = $this->createMock(PatrolPostService::class);
        $service->expects(self::never())->method('removePosts');
        $subscriber = new PatrolPostSubscriber($service);

        $subscriber->preRemove(new PreRemoveEventArgs(
            $this->patrol(OperationType::TRAINING),
            $this->createStub(EntityManagerInterface::class),
        ));
        $this->flush($subscriber, null);
    }

    public function testEachDeletionIsActedOnOnce(): void
    {
        $service = $this->createMock(PatrolPostService::class);
        $service->expects(self::once())->method('removePosts')->with(12);
        $subscriber = new PatrolPostSubscriber($service);

        $subscriber->preRemove(new PreRemoveEventArgs($this->patrol(), $this->createStub(EntityManagerInterface::class)));
        $this->flush($subscriber, null);
        $this->flush($subscriber, null);
    }
}
