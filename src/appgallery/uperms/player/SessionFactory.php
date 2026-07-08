<?php

declare(strict_types=1);

namespace appgallery\uperms\player;

use appgallery\uperms\group\GroupManager;

/**
 * Construye objetos PlayerSession desde datos crudos del storage.
 * Mantiene limpio al SessionManager de lógica de deserialización.
 */
final class SessionFactory {

    public function __construct(
        private readonly GroupManager $groupManager,
    ) {}

    /**
     * Valida y normaliza el array crudo antes de pasarlo a session->loadFrom().
     *
     * - Elimina rangos que apuntan a grupos inexistentes (con warning).
     * - Filtra nodos con permission vacío.
     * - Garantiza que el jugador siempre tenga al menos el rango default.
     *
     * @param array<string, mixed> $raw  Datos directos del storage
     * @return array<string, mixed>      Datos validados y normalizados
     */
    public function normalize(array $raw): array {
        // ── Validar rangos ────────────────────────────────────────────
        $groups = (array) ($raw['groups'] ?? []);

        foreach (array_keys($groups) as $groupId) {
            if (!$this->groupManager->get($groupId)) {
                \GlobalLogger::get()->warning(
                    "[UltimatePerms] Player '{$raw['username']}' has unknown group '{$groupId}' — removing."
                );
                unset($groups[$groupId]);
            }
        }

        // Garantizar rango default si se quedó sin ninguno
        if (empty($groups)) {
            $groups[$this->groupManager->getDefaultGroupId()] = null;
        }

        // ── Validar nodos ─────────────────────────────────────────────
        $permissions = array_values(
            array_filter(
                (array) ($raw['permissions'] ?? []),
                fn(array $n) => !empty($n['permission'] ?? $n['node'] ?? '')
            )
        );

        // Normalizar clave 'node' → 'permission' por si viene de YAML viejo
        $permissions = array_map(function (array $n): array {
            if (isset($n['node']) && !isset($n['permission'])) {
                $n['permission'] = $n['node'];
                unset($n['node']);
            }
            return $n;
        }, $permissions);

        return [
            ...$raw,
            'groups'       => $groups,
            'permissions' => $permissions,
            'prefix'      => $raw['prefix'] ?? null,
            'suffix'      => $raw['suffix'] ?? null,
            'meta'        => $raw['meta']   ?? [],
        ];
    }

    /**
     * Construye el array de datos de un jugador nuevo (primera vez que se conecta).
     */
    public function makeDefault(string $xuid, string $username): array {
        return [
            'xuid'        => $xuid,
            'username'    => $username,
            'groups'       => [$this->groupManager->getDefaultGroupId() => null],
            'permissions' => [],
            'prefix'      => null,
            'suffix'      => null,
            'meta'        => [],
        ];
    }
}