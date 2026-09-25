<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Tests\Unit\Service;

use DateTimeImmutable;
use MajesticDev\Discord\Service\PatrolPostFormatter;
use PHPUnit\Framework\TestCase;

class PatrolPostFormatterTest extends TestCase
{
    /**
     * @param array<string> $joined
     * @return array<string, mixed>
     */
    private function embed(array $joined = [], string $state = PatrolPostFormatter::OPEN, ?int $max = null): array
    {
        return PatrolPostFormatter::embed(
            12,
            'Night patrol',
            'https://forum.test/operations/12',
            new DateTimeImmutable('2026-09-26 20:00:00 UTC'),
            'Chernarus',
            'Cpl Doe',
            'Bring NVGs.',
            $joined,
            $max,
            $state,
        );
    }

    /**
     * @param array<string, mixed> $embed
     */
    private function field(array $embed, string $startsWith): ?string
    {
        foreach ($embed['fields'] as $field) {
            if (str_starts_with($field['name'], $startsWith)) {
                return $field['value'];
            }
        }
        return null;
    }

    public function testListsWhoJoined(): void
    {
        $embed = $this->embed(['Cpl Doe', 'Pvt Roe']);

        self::assertSame("- Cpl Doe\n- Pvt Roe", $this->field($embed, 'Joined'));
        self::assertNotNull($this->field($embed, 'Joined (2)'));
    }

    public function testSaysWhenNobodyHasJoined(): void
    {
        self::assertSame('Nobody yet.', $this->field($this->embed(), 'Joined'));
    }

    public function testShowsTheLimitWhenThereIsOne(): void
    {
        self::assertNotNull($this->field($this->embed(['Cpl Doe'], PatrolPostFormatter::OPEN, 10), 'Joined (1/10)'));
    }

    public function testACrowdedListIsCutShortWithinDiscordsFieldLimit(): void
    {
        $names = array_map(static fn (int $i) => "Member number $i of the very long roster", range(1, 80));

        $value = (string)$this->field($this->embed($names), 'Joined');

        self::assertLessThanOrEqual(1024, strlen($value));
        self::assertStringContainsString('more', $value);
        self::assertStringStartsWith('- Member number 1 ', $value);
    }

    public function testCarriesTheDetailsOfThePatrol(): void
    {
        $embed = $this->embed();

        self::assertSame('Night patrol', $embed['title']);
        self::assertSame('https://forum.test/operations/12', $embed['url']);
        self::assertSame('Bring NVGs.', $embed['description']);
        self::assertSame('Chernarus', $this->field($embed, 'Where'));
        self::assertSame('Cpl Doe', $this->field($embed, 'Leader'));
        self::assertStringContainsString('<t:' . (new DateTimeImmutable('2026-09-26 20:00:00 UTC'))->getTimestamp(), (string)$this->field($embed, 'When'));
        self::assertSame('Patrol #12', $embed['footer']['text']);
    }

    public function testMarksCancelledAndCompletedPatrols(): void
    {
        self::assertSame('Cancelled: Night patrol', $this->embed([], PatrolPostFormatter::CANCELLED)['title']);
        self::assertSame('Completed: Night patrol', $this->embed([], PatrolPostFormatter::COMPLETED)['title']);
    }

    public function testHasJoinLeaveAndSubmitAarButtonsForThePatrol(): void
    {
        $rows = PatrolPostFormatter::components(12);

        self::assertCount(1, $rows);
        self::assertSame(
            [['Join', 'patrol:join:12'], ['Leave', 'patrol:leave:12'], ['Submit AAR', 'patrol:aar:12']],
            array_map(static fn (array $b) => [$b['label'], $b['custom_id']], $rows[0]['components']),
        );
    }
}
