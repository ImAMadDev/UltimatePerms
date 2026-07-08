<?php

declare(strict_types=1);

namespace appgallery\uperms\player\context;

use pocketmine\player\Player;

interface IContextCalculator{

    /**
     * La clave que este calculador provee.
     * Ejemplos: "world", "gamemode", "faction", "arena"
     */
    public function getKey(): string;

    /**
     * El valor para este jugador ahora mismo.
     * Retorna null si el contexto no aplica al jugador en este momento.
     */
    public function getValueFor(Player $player): ?string;
}