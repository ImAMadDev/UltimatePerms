<?php

declare(strict_types=1);

namespace appgallery\uperms\permission;

use appgallery\uperms\permission\node\Node;
use appgallery\uperms\locale\LocaleManager;

/**
 * Resultado del trazado de origen de un permiso.
 * Usado por /uperms check y /uperms simulate.
 */
final class PermissionTrace{

    public const SOURCE_PERSONAL = 'personal';   // Override directo del jugador
    public const SOURCE_GROUP = 'group';       // Nodo directo del grupo
    public const SOURCE_INHERITED = 'inherited';   // Viene de un grupo padre
    public const SOURCE_DEFAULT = 'default';     // No definido en ningún lado

    public function __construct(
        public string  $permission,
        public bool    $state,
        public string  $source,
        public ?string $via,    // ID del grupo que lo otorga (null si personal o default)
        public ?Node   $node,   // El nodo exacto que lo resolvió (null si default)
    ){
    }

    public function isGranted(): bool{
        return $this->state;
    }

    public function isDenied(): bool{
        return !$this->state;
    }

    public function isDefault(): bool{
        return $this->source === self::SOURCE_DEFAULT;
    }

    /**
     * Descripción legible para mostrar en chat o consola.
     */
    public function describe(): string{
        return match ($this->source) {
            self::SOURCE_PERSONAL => $this->state
                ? LocaleManager::get('trace-granted-personal')
                : LocaleManager::get('trace-denied-personal'),

            self::SOURCE_GROUP => $this->state
                ? LocaleManager::get('trace-granted-group', ['group' => (string)$this->via])
                : LocaleManager::get('trace-denied-group', ['group' => (string)$this->via]),

            self::SOURCE_INHERITED => $this->state
                ? LocaleManager::get('trace-granted-inherited', ['group' => (string)$this->via])
                : LocaleManager::get('trace-denied-inherited', ['group' => (string)$this->via]),

            self::SOURCE_DEFAULT => LocaleManager::get('trace-default'),

            default => LocaleManager::get('trace-unknown'),
        };
    }
}