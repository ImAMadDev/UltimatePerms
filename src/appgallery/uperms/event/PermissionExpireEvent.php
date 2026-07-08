<?php

declare(strict_types=1);

namespace appgallery\uperms\event;

use appgallery\uperms\permission\node\Node;
use pocketmine\event\Event;
use pocketmine\player\Player;

/**
 * Disparado cuando un nodo de permiso temporal expira para un jugador.
 * No es cancelable — la expiración ya ocurrió.
 */
final class PermissionExpireEvent extends Event{

    public function __construct(
        private readonly Player $player,
        private readonly Node   $node,
    ){
    }

    public function getPlayer(): Player{
        return $this->player;
    }

    public function getNode(): Node{
        return $this->node;
    }

    public function getPermission(): string{
        return $this->node->getPermission();
    }
}