<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Service;

use League\Flysystem\FilesystemOperator;

class ImageGridBuilder
{
    private int $columns = 3;
    private int $cellWidth = 128;
    private ?int $cellHeight = 128;
    private int $padding = 16;

    /** @var list<array{storage: FilesystemOperator, path: string}> */
    private array $items = [];

    public function setColumns(int $columns): static
    {
        $this->columns = $columns;
        return $this;
    }

    public function setCellWidth(int $width): static
    {
        $this->cellWidth = $width;
        return $this;
    }

    public function setCellHeight(?int $height): static
    {
        $this->cellHeight = $height;
        return $this;
    }

    public function setCellSize(int $width, ?int $height): static
    {
        $this->cellWidth = $width;
        $this->cellHeight = $height;
        return $this;
    }

    public function setPadding(int $padding): static
    {
        $this->padding = $padding;
        return $this;
    }

    public function addImage(FilesystemOperator $storage, string $path): static
    {
        $this->items[] = ['storage' => $storage, 'path' => $path];
        return $this;
    }

    public function build(): ?string
    {
        if (empty($this->items)) {
            return null;
        }

        $count = count($this->items);
        $rows = (int)ceil($count / $this->columns);

        $loaded = [];
        foreach ($this->items as $index => $item) {
            $loaded[$index] = $this->loadImage($item['storage'], $item['path']);
        }

        $rowHeights = $this->resolveRowHeights($rows, $loaded);

        $canvasWidth = $this->cellWidth * $this->columns + $this->padding * ($this->columns - 1);
        $canvasHeight = array_sum($rowHeights) + $this->padding * max(0, $rows - 1);

        $canvas = imagecreatetruecolor(max(1, $canvasWidth), max(1, $canvasHeight));
        imagesavealpha($canvas, true);
        imagealphablending($canvas, false);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        if ($transparent !== false) {
            imagefill($canvas, 0, 0, $transparent);
        }
        imagealphablending($canvas, true);

        $rowY = 0;
        foreach (array_keys($this->items) as $index) {
            $col = $index % $this->columns;
            $row = (int)floor($index / $this->columns);

            if ($col === 0) {
                $rowY = 0;
                for ($r = 0; $r < $row; $r++) {
                    $rowY += $rowHeights[$r] + $this->padding;
                }
            }

            $x = $col * ($this->cellWidth + $this->padding);
            $y = $rowY;

            $source = $loaded[$index];
            $this->drawCell($canvas, $source, $x, $y, $rowHeights[$row]);
        }

        ob_start();
        imagewebp($canvas, null, 90);
        return (string)ob_get_clean();
    }

    /**
     * @param array<int, ?\GdImage> $loaded
     * @return array<int, int> row index => row height in pixels
     */
    private function resolveRowHeights(int $rows, array $loaded): array
    {
        $rowHeights = [];

        for ($row = 0; $row < $rows; $row++) {
            if ($this->cellHeight !== null) {
                $rowHeights[$row] = $this->cellHeight;
                continue;
            }

            $tallest = 0;
            for ($col = 0; $col < $this->columns; $col++) {
                $index = $row * $this->columns + $col;
                $source = $loaded[$index] ?? null;
                if ($source === null) {
                    continue;
                }

                $originalWidth = imagesx($source);
                $originalHeight = imagesy($source);
                $scale = $this->cellWidth / $originalWidth;
                $tallest = max($tallest, (int)round($originalHeight * $scale));
            }

            $rowHeights[$row] = max(1, $tallest);
        }

        return $rowHeights;
    }

    private function drawCell(\GdImage $canvas, ?\GdImage $source, int $x, int $y, int $targetHeight): void
    {
        if ($source === null) {
            return;
        }

        $originalWidth = imagesx($source);
        $originalHeight = imagesy($source);

        $scale = $this->cellHeight !== null
            ? min($this->cellWidth / $originalWidth, $targetHeight / $originalHeight)
            : $this->cellWidth / $originalWidth;

        $newWidth = max(1, (int)round($originalWidth * $scale));
        $newHeight = max(1, (int)round($originalHeight * $scale));

        // Center within cell
        $offsetX = $x + (int)round(($this->cellWidth - $newWidth) / 2);
        $offsetY = $y + (int)round(($targetHeight - $newHeight) / 2);

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagesavealpha($resized, true);
        imagealphablending($resized, false);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        if ($transparent !== false) {
            imagefill($resized, 0, 0, $transparent);
        }
        imagealphablending($resized, true);

        imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
        imagecopy($canvas, $resized, $offsetX, $offsetY, 0, 0, $newWidth, $newHeight);
    }

    private function loadImage(FilesystemOperator $storage, string $path): ?\GdImage
    {
        try {
            $data = $storage->read($path);
        } catch (\Throwable) {
            return null;
        }

        $image = @imagecreatefromstring($data);
        return $image !== false ? $image : null;
    }
}
