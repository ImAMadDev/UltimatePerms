<?php

declare(strict_types=1);

namespace appgallery\uperms\permission;

use appgallery\uperms\group\Group;
use appgallery\uperms\group\GroupRegistry;
use appgallery\uperms\permission\node\Node;
use appgallery\uperms\permission\node\NodeRegistry;

final class PermissionResolver {

    /**
     * Resuelve los permisos efectivos de una lista de grupos
     * respetando herencia, peso, wildcards, contexto y expiración.
     *
     * Retorna un mapa plano listo para pushear al PermissionAttachment.
     *
     * @param Group[]              $groups   Grupos del jugador (cualquier orden)
     * @param GroupRegistry        $registry Para resolver la herencia
     * @param array<string,string> $context  Contexto actual del jugador
     * @return array<string, bool>
     */
    public function resolveGroups(
        array         $groups,
        GroupRegistry $registry,
        array         $context = [],
    ): array {
        // Ordenar por weight DESC — el grupo de mayor peso tiene prioridad
        usort($groups, fn(Group $a, Group $b) => $b->getWeight() <=> $a->getWeight());

        // Mapa acumulado: permission → bool
        // El primer valor que se escribe para una clave gana (mayor peso primero)
        $resolved = [];

        foreach ($groups as $group) {
            // Obtener cadena completa de herencia: [grupo, padre, abuelo, ...]
            $visited = [];
            $chain   = $registry->resolveInheritance($group->getId(), $visited);

            foreach ($chain as $inherited) {
                $this->mergeNodes(
                    $inherited->getActiveNodes($context),
                    $resolved,
                    $context,
                );
            }
        }

        return $resolved;
    }

    /**
     * Resuelve un permiso concreto para un jugador dado sus nodos personales
     * y el mapa ya resuelto de grupos.
     *
     * Orden de prioridad:
     *   1. Override personal (false tiene máxima prioridad sobre todo)
     *   2. Mapa resuelto de grupos
     *   3. false por defecto si no hay nada
     *
     * @param Node[]               $personalNodes  Nodos directos del jugador
     * @param array<string, bool>  $groupResolved  Resultado de resolveGroups()
     * @param array<string, string> $context
     */
    public function resolvePermission(
        string $permission,
        array  $personalNodes,
        array  $groupResolved,
        array  $context = [],
    ): bool {
        // ── 1. Buscar override personal ───────────────────────────────
        $personal = $this->findCovering($permission, $personalNodes, $context);

        if ($personal !== null) {
            return $personal->getState();
        }

        // ── 2. Mapa de grupos ─────────────────────────────────────────
        return $groupResolved[$permission] ?? false;
    }

    /**
     * Construye el mapa completo final combinando grupos + overrides personales.
     * Es el método que llama applyResolved() en PlayerSession.
     *
     * @param array<string, bool>  $groupResolved
     * @param Node[]               $personalNodes
     * @param array<string, string> $context
     * @return array<string, bool>
     */
    public function buildFinal(
        array $groupResolved,
        array $personalNodes,
        array $context = [],
    ): array {
        $final = $groupResolved;

        foreach ($personalNodes as $node) {
            if (!$node->isActive()) {
                continue;
            }

            if (!$node->appliesIn($context)) {
                continue;
            }

            $perm = $node->getPermission();
            if ($node->isWildcard()) {
                NodeRegistry::getInstance()->registerWildcardIfNeeded($perm);
            }

            $final[$perm] = $node->getState();
        }

        return $final;
    }

    /**
     * Genera un informe de dónde viene un permiso concreto.
     * Usado por /uperms check y /uperms simulate.
     *
     * @param Node[]               $personalNodes
     * @param Group[]              $groups
     * @param array<string, string> $context
     */
    public function trace(
        string        $permission,
        array         $personalNodes,
        array         $groups,
        GroupRegistry $registry,
        array         $context = [],
    ): PermissionTrace {
        // ── Buscar en overrides personales ────────────────────────────
        $personal = $this->findCovering($permission, $personalNodes, $context);

        if ($personal !== null) {
            return new PermissionTrace(
                permission: $permission,
                state:      $personal->getState(),
                source:     PermissionTrace::SOURCE_PERSONAL,
                via:        null,
                node:       $personal,
            );
        }

        // ── Buscar en grupos por peso ─────────────────────────────────
        usort($groups, fn(Group $a, Group $b) => $b->getWeight() <=> $a->getWeight());

        foreach ($groups as $group) {
            $visited = [];
            $chain   = $registry->resolveInheritance($group->getId(), $visited);

            foreach ($chain as $inherited) {
                $covering = $this->findCovering(
                    $permission,
                    $inherited->getActiveNodes($context),
                    $context,
                );

                if ($covering !== null) {
                    return new PermissionTrace(
                        permission: $permission,
                        state:      $covering->getState(),
                        source:     $inherited->getId() === $group->getId()
                            ? PermissionTrace::SOURCE_GROUP
                            : PermissionTrace::SOURCE_INHERITED,
                        via:        $inherited->getId(),
                        node:       $covering,
                    );
                }
            }
        }

        // ── No definido ───────────────────────────────────────────────
        return new PermissionTrace(
            permission: $permission,
            state:      false,
            source:     PermissionTrace::SOURCE_DEFAULT,
            via:        null,
            node:       null,
        );
    }

    // ── Interno ───────────────────────────────────────────────────────

    /**
     * Fusiona un array de nodos en el mapa resuelto.
     * No sobreescribe claves ya presentes (el primer valor — mayor peso — gana),
     * EXCEPTO si el nuevo nodo es una negación explícita (false),
     * que siempre tiene máxima prioridad.
     *
     * @param Node[]               $nodes
     * @param array<string, bool>  $resolved  Mapa acumulado (modificado in-place)
     * @param array<string, string> $context
     */
    private function mergeNodes(array $nodes, array &$resolved, array $context): void {
        foreach ($nodes as $node) {
            if (!$node->isActive() || !$node->appliesIn($context)) {
                continue;
            }

            $perm = $node->getPermission();
            if ($node->isWildcard()) {
                NodeRegistry::getInstance()->registerWildcardIfNeeded($perm);
            }

            if (!isset($resolved[$perm]) || $node->getState() === false) {
                $resolved[$perm] = $node->getState();
            }
        }
    }

    /**
     * Busca el primer nodo activo que cubre el permiso dado en el contexto actual.
     *
     * @param Node[]               $nodes
     * @param array<string, string> $context
     */
    private function findCovering(string $permission, array $nodes, array $context): ?Node {
        // Primero buscar nodo literal exacto (más específico)
        foreach ($nodes as $node) {
            if (!$node->isActive() || !$node->appliesIn($context)) {
                continue;
            }

            if ($node->getPermission() === $permission) {
                return $node;
            }
        }

        // Luego buscar wildcard que lo cubra
        foreach ($nodes as $node) {
            if (!$node->isActive() || !$node->appliesIn($context)) {
                continue;
            }

            if ($node->isWildcard() && $node->covers($permission)) {
                return $node;
            }
        }

        return null;
    }
}