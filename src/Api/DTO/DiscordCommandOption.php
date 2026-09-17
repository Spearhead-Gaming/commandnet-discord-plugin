<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Api\DTO;

use Symfony\Component\Serializer\Attribute\Groups;

class DiscordCommandOption
{
    #[Groups('DiscordCommand')]
    public readonly string $type;

    #[Groups('DiscordCommand')]
    public string $name = '';

    #[Groups('DiscordCommand')]
    public string $description = '';

    #[Groups('DiscordCommand')]
    public bool $required = false;

    public function __construct(string $type = 'string')
    {
        $this->type = $type;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function setRequired(bool $required = true): static
    {
        $this->required = $required;
        return $this;
    }
}
