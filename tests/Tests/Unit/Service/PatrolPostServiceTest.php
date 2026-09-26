<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Tests\Unit\Service;

use DateTime;
use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Entity\Enum\OperationStatus;
use MajesticDev\CommandNet\Entity\Enum\OperationType;
use MajesticDev\CommandNet\Entity\Enum\RsvpStatus;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Entity\OperationRSVP;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\Discord\Api\Resource\DeleteMessage;
use MajesticDev\Discord\Api\Resource\EditMessage;
use MajesticDev\Discord\Api\Resource\PostMessage;
use MajesticDev\Discord\Entity\DiscordConnection;
use MajesticDev\Discord\Entity\PatrolMessage;
use MajesticDev\Discord\Exception\DiscordBotException;
use MajesticDev\Discord\Repository\DiscordConnectionRepository;
use MajesticDev\Discord\Repository\PatrolMessageRepository;
use MajesticDev\Discord\Service\BotService;
use MajesticDev\Discord\Service\PatrolPostService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionProperty;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PatrolPostServiceTest extends TestCase
{
    /** @var list<object> */
    private array $sent = [];

    /** @var list<PatrolMessage> */
    private array $saved = [];

    /** @var list<PatrolMessage> */
    private array $removed = [];

    protected function setUp(): void
    {
        // commandnet-plugin is an optional, un-required dependency (see phpstan.neon).
        if (!class_exists(Operation::class)) {
            $this->markTestSkipped('commandnet-plugin is not installed.');
        }
        $this->sent = $this->saved = $this->removed = [];
    }

    private function patrol(): Operation
    {
        $leader = new User();
        $leader->setDisplayName('Cpl Doe');

        $patrol = new Operation();
        (new ReflectionProperty($patrol, 'id'))->setValue($patrol, 12);
        $patrol->setType(OperationType::PATROL);
        $patrol->setTitle('Night patrol');
        $patrol->setStartDateTime(new DateTime('2026-09-26 20:00'));
        $patrol->setLeader($leader);
        return $patrol;
    }

    private function join(Operation $patrol, string $name, RsvpStatus $status = RsvpStatus::ATTENDING): void
    {
        $user = new User();
        $user->setDisplayName($name);
        $rsvp = new OperationRSVP($patrol, new SoldierProfile($user));
        $rsvp->setStatus($status);
        $patrol->getRsvps()->add($rsvp);
    }

    private function connection(?string $patrolsChannel, ?string $announcements = null): DiscordConnection
    {
        $connection = new DiscordConnection();
        $connection->setGuildId('g1');
        $connection->setPatrolsChannelId($patrolsChannel);
        $connection->setAnnouncementsChannelId($announcements);
        return $connection;
    }

    private function service(
        DiscordConnection $connection,
        ?PatrolMessage $existing = null,
        ?LoggerInterface $logger = null,
        bool $botFails = false,
        array $posts = [],
    ): PatrolPostService {
        $bot = $this->createStub(BotService::class);
        $bot->method('sendData')->willReturnCallback(function (object $payload) use ($botFails): void {
            if ($botFails) {
                throw new DiscordBotException('bot down');
            }
            $this->sent[] = $payload;
        });
        $bot->method('sendDataForResult')->willReturnCallback(function (object $payload) use ($botFails): array {
            if ($botFails) {
                throw new DiscordBotException('bot down');
            }
            $this->sent[] = $payload;
            return ['channelId' => 'c1', 'messageId' => 'm1'];
        });

        $connections = $this->createStub(DiscordConnectionRepository::class);
        $connections->method('findActive')->willReturn([$connection]);

        $messages = $this->createStub(PatrolMessageRepository::class);
        $messages->method('findForPatrol')->willReturn($existing);
        $messages->method('findAllForPatrol')->willReturn($posts);
        $messages->method('save')->willReturnCallback(function (PatrolMessage $m): void {
            $this->saved[] = $m;
        });
        $messages->method('remove')->willReturnCallback(function (PatrolMessage $m): void {
            $this->removed[] = $m;
        });

        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('https://forum.test/operations/12');

        return new PatrolPostService(
            $bot,
            $connections,
            $messages,
            $urls,
            $logger ?? $this->createStub(LoggerInterface::class),
        );
    }

    public function testDeletingAPatrolDeletesItsPostsFromEveryServerAndForgetsThem(): void
    {
        $posts = [new PatrolMessage(12, 'g1', 'c1', 'm1'), new PatrolMessage(12, 'g2', 'c2', 'm2')];

        $this->service($this->connection('c1'), null, null, false, $posts)->removePosts(12);

        self::assertCount(2, $this->sent);
        self::assertContainsOnlyInstancesOf(DeleteMessage::class, $this->sent);
        self::assertSame(
            [['g1', 'c1', 'm1'], ['g2', 'c2', 'm2']],
            array_map(static fn (DeleteMessage $d) => [$d->guildId, $d->channelId, $d->messageId], $this->sent),
        );
        self::assertSame($posts, $this->removed);
    }

    public function testAPostThatCannotBeDeletedIsLoggedAndStillForgotten(): void
    {
        $posts = [new PatrolMessage(12, 'g1', 'c1', 'm1')];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $this->service($this->connection('c1'), null, $logger, true, $posts)->removePosts(12);

        self::assertSame($posts, $this->removed);
    }

    public function testDeletingAPatrolThatWasNeverPostedSendsNothing(): void
    {
        $this->service($this->connection('c1'))->removePosts(12);

        self::assertSame([], $this->sent);
        self::assertSame([], $this->removed);
    }

    public function testANewPatrolIsPostedWithButtonsAndRemembered(): void
    {
        $this->service($this->connection('c1'))->sync($this->patrol(), true);

        self::assertCount(1, $this->sent);
        $post = $this->sent[0];
        self::assertInstanceOf(PostMessage::class, $post);
        self::assertSame('c1', $post->channelId);
        self::assertSame('Night patrol', $post->embed['title']);
        self::assertSame('patrol:join:12', $post->components[0]['components'][0]['custom_id']);

        self::assertCount(1, $this->saved);
        self::assertSame([12, 'g1', 'c1', 'm1'], [
            $this->saved[0]->getOperationId(),
            $this->saved[0]->getGuildId(),
            $this->saved[0]->getChannelId(),
            $this->saved[0]->getMessageId(),
        ]);
    }

    public function testThePostListsTheMembersWhoJoined(): void
    {
        $patrol = $this->patrol();
        $this->join($patrol, 'Pvt Roe');
        $this->join($patrol, 'Cpl Doe');
        $this->join($patrol, 'Sgt Nope', RsvpStatus::DECLINED);

        $this->service($this->connection('c1'))->sync($patrol, true);

        $joined = null;
        foreach ($this->sent[0]->embed['fields'] as $field) {
            if (str_starts_with($field['name'], 'Joined')) {
                $joined = $field;
            }
        }
        self::assertSame('Joined (2)', $joined['name']);
        self::assertSame("- Cpl Doe\n- Pvt Roe", $joined['value']);
    }

    public function testAChangeEditsTheExistingPostInsteadOfPostingAgain(): void
    {
        $existing = new PatrolMessage(12, 'g1', 'c1', 'm1');
        $patrol = $this->patrol();
        $this->join($patrol, 'Cpl Doe');

        $this->service($this->connection('c1'), $existing)->sync($patrol, false);

        self::assertCount(1, $this->sent);
        $edit = $this->sent[0];
        self::assertInstanceOf(EditMessage::class, $edit);
        self::assertSame(['c1', 'm1'], [$edit->channelId, $edit->messageId]);
        self::assertNotSame([], $edit->components);
        self::assertSame([], $this->saved);
    }

    public function testCancellingRemovesTheButtons(): void
    {
        $patrol = $this->patrol();
        $patrol->setStatus(OperationStatus::CANCELLED);

        $this->service($this->connection('c1'), new PatrolMessage(12, 'g1', 'c1', 'm1'))->sync($patrol, false);

        self::assertSame([], $this->sent[0]->components);
        self::assertSame('Cancelled: Night patrol', $this->sent[0]->embed['title']);
    }

    public function testACompletedPatrolLosesItsButtons(): void
    {
        $patrol = $this->patrol();
        $patrol->setStatus(OperationStatus::COMPLETED);

        $this->service($this->connection('c1'), new PatrolMessage(12, 'g1', 'c1', 'm1'))->sync($patrol, false);

        self::assertSame([], $this->sent[0]->components);
    }

    public function testAServerWithoutAPatrolsChannelGetsThePlainAnnouncement(): void
    {
        $this->service($this->connection(null, 'a1'))->sync($this->patrol(), true);

        self::assertCount(1, $this->sent);
        self::assertSame('a1', $this->sent[0]->channelId);
        self::assertStringContainsString('New patrol: Night patrol', $this->sent[0]->content);
        self::assertNull($this->sent[0]->components);
        self::assertSame([], $this->saved);
    }

    public function testNothingIsPostedForAnOldPatrolWithNoPostYet(): void
    {
        $this->service($this->connection('c1'))->sync($this->patrol(), false);

        self::assertSame([], $this->sent);
    }

    public function testACancelledNewPatrolIsNotPosted(): void
    {
        $patrol = $this->patrol();
        $patrol->setStatus(OperationStatus::CANCELLED);

        $this->service($this->connection('c1'))->sync($patrol, true);

        self::assertSame([], $this->sent);
    }

    public function testADiscordFailureIsLoggedNotThrown(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $this->service($this->connection('c1'), null, $logger, true)->sync($this->patrol(), true);

        self::assertSame([], $this->saved);
    }
}
