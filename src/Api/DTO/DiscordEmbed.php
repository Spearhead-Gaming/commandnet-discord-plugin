<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Api\DTO;

use Symfony\Component\Serializer\Attribute\Groups;

class DiscordEmbed
{
    /** @var array{url: string} */
    #[Groups('DiscordCommandRun')]
    public ?array $thumbnail = null;

    /** @var array{url: string} */
    #[Groups('DiscordCommandRun')]
    public ?array $image = null;

    /** @var array{text: string} */
    #[Groups('DiscordCommandRun')]
    public ?array $footer = null;

    /** @var list<array{ name: string, value: string, inline?: bool }> */
    #[Groups('DiscordCommandRun')]
    public ?array $fields = null;

    /** @var array{ name: string, icon_url: string, url: string } */
    #[Groups('DiscordCommandRun')]
    public ?array $author = null;

    public function __construct(
        #[Groups('DiscordCommandRun')]
        public ?string $title = null,
        #[Groups('DiscordCommandRun')]
        public ?string $description = null,
        #[Groups('DiscordCommandRun')]
        public ?string $url = null,
    ) {
    }

    public function setThumbnail(string $thumbnail): static
    {
        $this->thumbnail = ['url' => $thumbnail];
        return $this;
    }

    public function setImage(string $image): static
    {
        $this->image = ['url' => $image];
        return $this;
    }

    public function addField(string $name = '', string $value = '', bool $inline = false): static
    {
        $this->fields[] = ['name' => $name, 'value' => $value, 'inline' => $inline];
        return $this;
    }

    public function setFooter(string $text): static
    {
        $this->footer = ['text' => $text];
        return $this;
    }
}
