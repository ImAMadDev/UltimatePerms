<?php

declare(strict_types=1);

namespace appgallery\uperms\group;

use pocketmine\utils\SingletonTrait;

final class GroupRegistry{
    use SingletonTrait;

    /** @var array<string, Group> */
    private array $groups = [];

    // ── CRUD ──────────────────────────────────────────────────────────

    public function register(Group $group): void{
        $this->groups[$group->getId()] = $group;
    }

    public function unregister(string $id): void{
        unset($this->groups[$id]);
    }

    public function get(string $id): ?Group{
        return $this->groups[$id] ?? null;
    }

    public function has(string $id): bool{
        return isset($this->groups[$id]);
    }

    /** @return Group[] */
    public function getAll(): array{
        return array_values($this->groups);
    }

    /** @return string[] */
    public function getAllIds(): array{
        return array_keys($this->groups);
    }

    public function count(): int{
        return count($this->groups);
    }

    // ── Utilidades ────────────────────────────────────────────────────

    /**
     * Grupos ordenados por weight DESC.
     * @return Group[]
     */
    public function getAllByWeight(): array{
        $groups = array_values($this->groups);
        usort($groups, fn(Group $a, Group $b) => $b->getWeight() <=> $a->getWeight());
        return $groups;
    }

    /**
     * Retorna los grupos que tienen como padre al grupo dado.
     * Con un solo padre la comparación es directa — sin array_filter sobre array.
     *
     * @return Group[]
     */
    public function getChildren(string $parentId): array{
        return array_values(
            array_filter(
                $this->groups,
                fn(Group $g) => $g->getParent() === $parentId
            )
        );
    }

    /**
     * Resuelve la cadena completa de herencia (cadena lineal con un solo padre).
     * Retorna: [grupo, padre, abuelo, ...]
     * Detecta ciclos con $visited para no entrar en loop infinito.
     *
     * @param string[] $visited
     * @return Group[]
     */
    public function resolveInheritance(string $groupId, array &$visited = []): array{
        if(in_array($groupId, $visited, true)){
            return []; // Ciclo detectado — cortar
        }

        $group = $this->get($groupId);
        if($group === null){
            return [];
        }

        $visited[] = $groupId;
        $resolved = [$group];

        $parentId = $group->getParent();
        if($parentId !== null){
            array_push($resolved, ...$this->resolveInheritance($parentId, $visited));
        }

        return $resolved;
    }

    /**
     * Detecta si existe un ciclo en la herencia del grupo dado.
     * Con un solo padre la cadena es lineal, pero igual puede haber
     * un ciclo si A→B y B→A.
     */
    public function hasCycle(string $groupId): bool{
        $visited = [];
        return $this->detectCycle($groupId, $visited);
    }

    private function detectCycle(string $groupId, array &$visited): bool{
        if(in_array($groupId, $visited, true)){
            return true;
        }

        $group = $this->get($groupId);
        if($group === null){
            return false;
        }

        $visited[] = $groupId;

        $parentId = $group->getParent();
        if($parentId !== null && $this->detectCycle($parentId, $visited)){
            return true;
        }

        array_pop($visited);
        return false;
    }

    public function clear(): void{
        $this->groups = [];
    }
}