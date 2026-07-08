<?php

declare(strict_types=1);

namespace appgallery\uperms\event;

use appgallery\uperms\group\Group;
use appgallery\uperms\permission\node\Node;
use pocketmine\event\Event;

/**
 * Disparado cuando un nodo temporal de un grupo expira.
 * No es cancelable — la expiración ya ocurrió.
 */
final class GroupPermExpireEvent extends Event{

    public function __construct(
        private readonly Group $group,
        private readonly Node  $node,
    ){
    }

    public function getGroup(): Group{
        return $this->group;
    }

    public function getNode(): Node{
        return $this->node;
    }

    public function getPermission(): string{
        return $this->node->getPermission();
    }
}