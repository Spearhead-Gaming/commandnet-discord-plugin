<?php

declare(strict_types=1);

namespace MajesticDev\Discord\Exception;

class NoBotRegisteredException extends DiscordBotException
{
    public function __construct()
    {
        parent::__construct("Discord bot has not been registered yet.");
    }
}
