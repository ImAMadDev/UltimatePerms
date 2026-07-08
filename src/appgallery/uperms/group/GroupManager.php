<?php

declare(strict_types=1);

namespace appgallery\uperms\group;

use appgallery\uperms\storage\IStorage;
use pocketmine\utils\TextFormat;

final class GroupManager{

    private const DEFAULT_GROUP_ID = 'default';

    public function __construct(
        private readonly GroupRegistry $registry,
        private readonly IStorage      $storage,
    ){
    }

    // ── Boot ──────────────────────────────────────────────────────────

    /**
     * Carga todos los grupos desde storage al registry.
     * Si no existe ninguno, crea el grupo "default" automáticamente.
     * Debe llamarse en onEnable() antes de que los jugadores puedan conectarse.
     */
    public function loadAll(): void{
        $groups = $this->storage->loadGroups();

        foreach($groups as $group){
            $this->validateAndRegister($group);
        }

        if(!$this->registry->has(self::DEFAULT_GROUP_ID)){
            \GlobalLogger::get()->warning(
                TextFormat::YELLOW . "[UltimatePerms] Default group not found — creating it automatically."
            );
            $this->create(self::DEFAULT_GROUP_ID, '§7Default', 0);
        }

        \GlobalLogger::get()->info(
            TextFormat::GREEN . "[UltimatePerms] Loaded {$this->registry->count()} groups."
        );
    }

    /**
     * Valída el grupo antes de registrarlo:
     * - Elimina padres que no existen (warning en log).
     * - Detecta ciclos de herencia (error en log, rompe el ciclo).
     */
    private function validateAndRegister(Group $group): void{
        // Validar padre existente
        if(!$this->registry->has($group->getParent())){
            \GlobalLogger::get()->warning(
                TextFormat::YELLOW . "[UltimatePerms] Group '{$group->getId()}' references " .
                "unknown parent '{$group->getParent()}' — ignoring."
            );
            $group->setParent(null);

        }

        $this->registry->register($group);

        // Detectar ciclos DESPUÉS de registrar
        if($this->registry->hasCycle($group->getId())){
            \GlobalLogger::get()->error(
                TextFormat::RED . "[UltimatePerms] Inheritance cycle detected in group '{$group->getId()}' " .
                "— clearing parent to prevent infinite loops."
            );
            $group->setParent(null);

            $this->storage->saveGroup($group);
        }
    }

    // ── CRUD ──────────────────────────────────────────────────────────

    public function create(string $id, string $displayName, int $weight = 0): ?Group{
        if($this->registry->has($id)){
            return null; // Ya existe
        }

        $group = new Group(
            id: $id,
            displayName: $displayName,
            weight: $weight,
        );

        $this->registry->register($group);
        $this->storage->saveGroup($group);

        return $group;
    }

    public function delete(string $id): bool{
        if($id === self::DEFAULT_GROUP_ID){
            return false; // El grupo default nunca se puede eliminar
        }

        $group = $this->registry->get($id);
        if($group === null){
            return false;
        }

        // Limpiar referencias de herencia en grupos hijos
        foreach($this->registry->getChildren($id) as $child){
            $child->setParent(null);
            $this->storage->saveGroup($child);
        }

        $this->registry->unregister($id);
        $this->storage->deleteGroup($id);

        return true;
    }

    public function clone(string $sourceId, string $newId, string $newDisplayName): ?Group{
        $source = $this->registry->get($sourceId);
        if($source === null || $this->registry->has($newId)){
            return null;
        }

        $cloned = Group::deserialize([
            ...$source->serialize(),
            'id' => $newId,
            'displayName' => $newDisplayName,
        ]);

        $this->registry->register($cloned);
        $this->storage->saveGroup($cloned);

        return $cloned;
    }

    public function save(Group $group): void{
        $this->storage->saveGroup($group);
    }

    public function saveAll(): void{
        foreach($this->registry->getAll() as $group){
            $this->storage->saveGroup($group);
        }
    }

    // ── Getters de conveniencia ────────────────────────────────────────

    public function get(string $id): ?Group{
        return $this->registry->get($id);
    }

    public function getDefault(): Group{
        return $this->registry->get(self::DEFAULT_GROUP_ID)
            ?? throw new \RuntimeException("Default group is missing — this should never happen.");
    }

    public function getRegistry(): GroupRegistry{
        return $this->registry;
    }

    public function getDefaultGroupId(): string{
        return self::DEFAULT_GROUP_ID;
    }

    public function getAllIds(): array{
        return array_keys($this->registry->getAll());
    }
}