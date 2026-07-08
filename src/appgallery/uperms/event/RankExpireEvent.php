<?php

declare(strict_types=1);

namespace appgallery\uperms\event;

use pocketmine\event\Event;
use pocketmine\player\Player;

/**
 * Disparado cuando un rango temporal expira para un jugador.
 * No es cancelable — la expiración ya ocurrió.
 */
final class GroupExpireEvent extends Event{

    public function __construct(
        private readonly Player $player,
        private readonly string $groupId,
    ){
    }

    public function getPlayer(): Player{
        return $this->player;
    }

    public function getGroupId(): string{
        return $this->groupId;
    }
}