<?php

declare(strict_types=1);

namespace appgallery\uperms\player\context;

use pocketmine\player\Player;

final class WorldContextCalculator implements IContextCalculator{

    public function getKey(): string{
        return 'world';
    }

    public function getValueFor(Player $player): ?string{
        return $player->getWorld()->getFolderName();
    }
}