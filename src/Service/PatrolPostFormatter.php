<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Service;

use DateTimeInterface;

/**
 * Builds the Discord embed and buttons for a patrol post. Plain data in, plain arrays out, so
 * it does not depend on commandnet-plugin (an optional dependency here) and is easy to test.
 */
final class PatrolPostFormatter
{
    public const string OPEN = 'open';
    public const string CANCELLED = 'cancelled';
    public const string COMPLETED = 'completed';

    /** Discord caps an embed field value at 1024 characters. */
    private const int MAX_NAMES_LENGTH = 1000;
    private const int MAX_DETAILS_LENGTH = 1500;

    private const array COLOURS = [
        self::OPEN => 0x3BA55D,
        self::CANCELLED => 0xED4245,
        self::COMPLETED => 0x5865F2,
    ];

    /**
     * @param array<string> $joined display names of the members who joined
     * @return array<string, mixed>
     */
    public static function embed(
        int $patrolId,
        string $title,
        string $url,
        DateTimeInterface $start,
        ?string $location,
        ?string $leader,
        ?string $details,
        array $joined,
        ?int $maxParticipants,
        string $state,
    ): array {
        $timestamp = $start->getTimestamp();
        $fields = [['name' => 'When', 'value' => "<t:$timestamp:F> (<t:$timestamp:R>)", 'inline' => false]];
        if ($location !== null && $location !== '') {
            $fields[] = ['name' => 'Where', 'value' => $location, 'inline' => true];
        }
        if ($leader !== null && $leader !== '') {
            $fields[] = ['name' => 'Leader', 'value' => $leader, 'inline' => true];
        }

        $count = count($joined);
        $fields[] = [
            'name' => $maxParticipants !== null ? "Joined ($count/$maxParticipants)" : "Joined ($count)",
            'value' => self::names($joined),
            'inline' => false,
        ];

        $prefix = match ($state) {
            self::CANCELLED => 'Cancelled: ',
            self::COMPLETED => 'Completed: ',
            default => '',
        };

        $embed = [
            'title' => $prefix . $title,
            'url' => $url,
            'color' => self::COLOURS[$state] ?? self::COLOURS[self::OPEN],
            'fields' => $fields,
            'footer' => ['text' => "Patrol #$patrolId"],
        ];
        if ($details !== null && trim($details) !== '') {
            $embed['description'] = mb_strimwidth(trim($details), 0, self::MAX_DETAILS_LENGTH, '...');
        }

        return $embed;
    }

    /**
     * @return array<int, array<string, mixed>> one action row, in Discord's JSON format
     */
    public static function components(int $patrolId): array
    {
        $button = static fn (int $style, string $label, string $action): array => [
            'type' => 2,
            'style' => $style,
            'label' => $label,
            'custom_id' => "patrol:$action:$patrolId",
        ];

        return [[
            'type' => 1,
            'components' => [
                $button(3, 'Join', 'join'),
                $button(2, 'Leave', 'leave'),
                $button(1, 'Submit AAR', 'aar'),
            ],
        ]];
    }

    /**
     * @param array<string> $names
     */
    private static function names(array $names): string
    {
        if ($names === []) {
            return 'Nobody yet.';
        }

        $lines = [];
        $length = 0;
        foreach ($names as $i => $name) {
            $line = '- ' . $name;
            if ($length + strlen($line) > self::MAX_NAMES_LENGTH) {
                $lines[] = '...and ' . (count($names) - $i) . ' more';
                break;
            }
            $lines[] = $line;
            $length += strlen($line) + 1;
        }

        return implode("\n", $lines);
    }
}
