<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Service;

use Forumify\Forum\Entity\Badge;
use League\Flysystem\FilesystemOperator;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class BadgeImageComposer
{
    public function __construct(
        private readonly FilesystemOperator $assetStorage,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * @param non-empty-array<Badge> $badges
     */
    public function compose(array $badges): string
    {
        $cacheKey = 'discord_badge_composite_' . implode('_', array_map(
            fn (Badge $badge) => $badge->getId(),
            $badges,
        ));

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($badges): string {
            $item->expiresAfter(null);
            return $this->generate($badges);
        });
    }

    /**
     * @param array<Badge> $badges
     */
    private function generate(array $badges): string
    {
        $builder = (new ImageGridBuilder())
            ->setColumns(3)
            ->setCellSize(128, 128)
            ->setPadding(16);

        foreach ($badges as $badge) {
            $builder->addImage($this->assetStorage, $badge->getImage());
        }

        $filename = 'discord/badges/' . md5(implode('_', array_map(
            fn (Badge $badge) => $badge->getId(),
            $badges,
        ))) . '.webp';

        $this->assetStorage->write($filename, (string)$builder->build());

        return $filename;
    }
}
