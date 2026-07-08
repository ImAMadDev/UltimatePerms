<?php

declare(strict_types=1);

namespace appgallery\uperms\player\context;

use pocketmine\player\GameMode;
use pocketmine\player\Player;

final class GamemodeContextCalculator implements IContextCalculator{

    public function getKey(): string{
        return 'gamemode';
    }

    public function getValueFor(Player $player): ?string{
        return mb_strtolower($player->getGamemode()->name);
    }
}