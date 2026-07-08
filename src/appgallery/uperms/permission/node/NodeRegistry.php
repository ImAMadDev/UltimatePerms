<?php

declare(strict_types=1);

namespace appgallery\uperms\permission\node;

use pocketmine\permission\Permission;
use pocketmine\permission\PermissionManager;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\TextFormat;

class NodeRegistry {
    use SingletonTrait;

    /**
     * Todos los nodos del servidor indexados por nombre.
     * @var array<string, Node>
     */
    private array $nodes = [];

    public function load(): void {
        \GlobalLogger::get()->info(TextFormat::DARK_AQUA . "Loading nodes...");

        foreach (PermissionManager::getInstance()->getPermissions() as $perm) {
            $this->registerRecursive($perm);
        }

        \GlobalLogger::get()->info(
            TextFormat::GREEN . "Loaded " . count($this->nodes) . " permission nodes."
        );
    }

    private function registerRecursive(Permission $perm): void {
        $name = $perm->getName();

        if (isset($this->nodes[$name])) {
            return;
        }

        // El default del Permission de PMMP es 'op' o 'true'/'false'
        // Lo registramos como true (es un nodo conocido del servidor, no asignado a nadie aún)
        $this->nodes[$name] = new Node(
            permission: $name,
            state:      true,
            expiresAt:  null,
            context:    [],
        );

        \GlobalLogger::get()->info(TextFormat::DARK_AQUA . "  Registered node: {$name}");

        foreach ($perm->getChildren() as $childName => $childState) {
            $childPerm = PermissionManager::getInstance()->getPermission($childName);

            if ($childPerm !== null) {
                $this->registerRecursive($childPerm);
            } else {
                // Child sin objeto Permission propio — registrar con el state que PMMP definió
                if (!isset($this->nodes[$childName])) {
                    $this->nodes[$childName] = new Node(
                        permission: $childName,
                        state:      $childState,
                        expiresAt:  null,
                        context:    [],
                    );
                    \GlobalLogger::get()->info(TextFormat::GRAY . "  Registered child node: {$childName}");
                }
            }
        }
    }

    // ── Registro manual (para plugins que registran nodos en runtime) ─

    /**
     * Registra un Node externo (ej: nodos definidos por UltimatePerms u otros plugins).
     */
    public function register(Node $node): void {
        $this->nodes[$node->getPermission()] = $node;
    }

    /**
     * Registra múltiples nodos de golpe.
     * @param Node[] $nodes
     */
    public function registerBatch(array $nodes): void {
        foreach ($nodes as $node) {
            $this->register($node);
        }
    }

    // ── Consultas ─────────────────────────────────────────────────────

    public function get(string $permission): ?Node {
        return $this->nodes[$permission] ?? null;
    }

    public function isKnown(string $permission): bool {
        return isset($this->nodes[$permission]);
    }

    /**
     * @return Node[]
     */
    public function getAll(): array {
        return array_values($this->nodes);
    }

    // ── Wildcard ──────────────────────────────────────────────────────

    /**
     * Expande un wildcard y retorna los Node[] que cubre.
     * Delega en Node::covers() para mantener la lógica centralizada.
     *
     * Ejemplos:
     *   "pocketmine.command.time.*" → [Node(time.add), Node(time.set), ...]
     *   "*"                         → todos los nodos registrados
     *
     * @return Node[]
     */
    public function expandWildcard(string $wildcard): array {
        // Nodo literal — retornar solo él si existe
        if (!str_ends_with($wildcard, '.*') && $wildcard !== '*') {
            $node = $this->get($wildcard);
            return $node !== null ? [$node] : [];
        }

        $template = new Node(permission: $wildcard, state: true);

        return array_values(
            array_filter(
                $this->nodes,
                fn(Node $node) => $template->covers($node->getPermission())
            )
        );
    }

    /**
     * Igual que expandWildcard() pero retorna solo los nombres.
     * @return string[]
     */
    public function expandWildcardNames(string $wildcard): array {
        return array_map(
            fn(Node $node) => $node->getPermission(),
            $this->expandWildcard($wildcard)
        );
    }

    /**
     * Verifica si un permiso concreto es cubierto por algún Node wildcard en la lista dada.
     * Útil en el resolver para saber si un jugador tiene un wildcard que cubre el permiso pedido.
     *
     * @param Node[] $assignedNodes  Los nodos asignados al jugador/grupo
     */
    public function isCoveredByAny(string $permission, array $assignedNodes): ?Node {
        foreach ($assignedNodes as $node) {
            if ($node->isActive() && $node->covers($permission)) {
                return $node; // retorna el Node que lo cubre para saber su state
            }
        }
        return null;
    }

    public function getAllPermissionNames(): array{
        return array_keys($this->nodes);
    }

    /**
     * Asegura que un permiso wildcard (como pocketmine.command.* o *)
     * esté registrado en el PermissionManager de PocketMine-MP y tenga
     * a todos los permisos correspondientes como hijos.
     */
    public function registerWildcardIfNeeded(string $wildcard): void {
        $pm = PermissionManager::getInstance();
        $perm = $pm->getPermission($wildcard);

        if ($wildcard === '*') {
            if ($perm === null) {
                $perm = new Permission('*');
                $pm->addPermission($perm);
            }
            // Sincronizar todos los permisos conocidos como hijos de *
            foreach ($pm->getPermissions() as $knownPerm) {
                $name = $knownPerm->getName();
                if ($name !== '*' && !isset($perm->getChildren()[$name])) {
                    $perm->addChild($name, true);
                }
            }
            return;
        }

        if (!str_ends_with($wildcard, '.*')) {
            return;
        }

        if ($perm === null) {
            $perm = new Permission($wildcard);
            $pm->addPermission($perm);
        }

        // Sincronizar permisos conocidos que coincidan con el prefijo
        $prefix = substr($wildcard, 0, -1); // "myplugin."
        foreach ($pm->getPermissions() as $knownPerm) {
            $name = $knownPerm->getName();
            if (str_starts_with($name, $prefix) && $name !== $wildcard && !isset($perm->getChildren()[$name])) {
                $perm->addChild($name, true);
            }
        }
    }
}