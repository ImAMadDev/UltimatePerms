<?php

declare(strict_types=1);

namespace appgallery\uperms\player;

use appgallery\uperms\permission\node\Node;
use appgallery\uperms\permission\node\NodeRegistry;
use pocketmine\permission\PermissionAttachment;
use pocketmine\player\Player;

final class PlayerSession{

    /**
     * Nodos resueltos en el attachment de PMMP.
     * Se recalculan cuando cambia el contexto o se modifica un nodo/rango.
     * @var array<string, bool>
     */
    private array $resolvedPermissions = [];

    /**
     * Contexto actual del jugador.
     * @var array<string, string>
     */
    private array $context = [];

    /**
     * Nodos asignados directamente al jugador (overrides personales).
     * @var Node[]
     */
    private array $personalNodes = [];

    /**
     * IDs de grupos/rangos del jugador con su expiración.
     * @var array<string, int|null>   [groupId => expiresAt|null]
     */
    private array $groups = [];

    /**
     * Prefijo resuelto (personal > rango primario > herencia).
     */
    private string $prefix = '';

    /**
     * Sufijo resuelto.
     */
    private string $suffix = '';

    /**
     * Meta key-value del jugador (sobreescribe la del rango).
     * @var array<string, string>
     */
    private array $meta = [];

    /**
     * Marca si hay cambios pendientes de persistir en storage.
     */
    private bool $dirty = false;

    public function __construct(
        private readonly Player               $player,
        private readonly PermissionAttachment $attachment,
    ){
    }

    // ── Contexto ─────────────────────────────────────────────────────

    /**
     * @param array<string, string> $context
     */
    public function setContext(array $context): void{
        $this->context = $context;
        $this->recalculate();
    }

    public function updateContext(string $key, string $value): void{
        if(($this->context[$key] ?? null) === $value){
            return; // Sin cambio, no recalcular
        }

        $this->context[$key] = $value;
        $this->recalculate();
    }

    public function removeContext(string $key): void{
        if(!isset($this->context[$key])){
            return;
        }

        unset($this->context[$key]);
        $this->recalculate();
    }

    /**
     * @return array<string, string>
     */
    public function getContext(): array{
        return $this->context;
    }

    // ── Rangos ───────────────────────────────────────────────────────

    /**
     * @param int|null $expiresAt null = permanente
     */
    public function addGroup(string $groupId, ?int $expiresAt = null): void{
        $this->groups[$groupId] = $expiresAt;
        $this->dirty = true;
        $this->recalculate();
    }

    public function removeGroup(string $groupId): void{
        if(!array_key_exists($groupId, $this->groups)){
            return;
        }

        unset($this->groups[$groupId]);
        $this->dirty = true;
        $this->recalculate();
    }

    public function hasGroup(string $groupId): bool{
        return array_key_exists($groupId, $this->groups);
    }

    /**
     * @return array<string, int|null>
     */
    public function getGroups(): array{
        return $this->groups;
    }

    /**
     * @return PermissionAttachment
     */
    public function getAttachment(): PermissionAttachment{
        return $this->attachment;
    }

    /**
     * Limpia los rangos expirados y retorna los IDs que expiraron.
     * @return string[]
     */
    public function flushExpiredGroups(): array{
        $expired = [];

        foreach($this->groups as $groupId => $expiresAt){
            if($expiresAt !== null && time() > $expiresAt){
                $expired[] = $groupId;
                unset($this->groups[$groupId]);
            }
        }

        if(!empty($expired)){
            $this->dirty = true;
            $this->recalculate();
        }

        return $expired;
    }

    // ── Nodos personales ─────────────────────────────────────────────

    public function addNode(Node $node): void{
        // Si ya existe un nodo para ese permiso, reemplazarlo
        foreach($this->personalNodes as $i => $existing){
            if($existing->getPermission() === $node->getPermission()){
                $this->personalNodes[$i] = $node;
                $this->dirty = true;
                $this->recalculate();
                return;
            }
        }

        $this->personalNodes[] = $node;
        $this->dirty = true;
        $this->recalculate();
    }

    public function removeNode(string $permission): void{
        foreach($this->personalNodes as $i => $node){
            if($node->getPermission() === $permission){
                array_splice($this->personalNodes, $i, 1);
                $this->dirty = true;
                $this->recalculate();
                return;
            }
        }
    }

    public function getPersonalNode(string $permission): ?Node{
        foreach($this->personalNodes as $node){
            if($node->getPermission() === $permission){
                return $node;
            }
        }
        return null;
    }

    /**
     * Limpia los nodos expirados y retorna los que expiraron.
     * @return Node[]
     */
    public function flushExpiredNodes(): array{
        $expired = [];
        $remaining = [];

        foreach($this->personalNodes as $node){
            if($node->isExpired()){
                $expired[] = $node;
            } else{
                $remaining[] = $node;
            }
        }

        if(!empty($expired)){
            $this->personalNodes = $remaining;
            $this->dirty = true;
            $this->recalculate();
        }

        return $expired;
    }

    /**
     * @return Node[]
     */
    public function getPersonalNodes(): array{
        return $this->personalNodes;
    }

    // ── Resolución de permisos ────────────────────────────────────────

    /**
     * Recalcula todos los permisos efectivos y actualiza el attachment de PMMP.
     * Llamado automáticamente al cambiar contexto, rangos o nodos.
     * También puede ser llamado externamente por el PermissionResolver.
     *
     * @param array<string, bool> $resolvedFromGroups Permisos ya resueltos por el PermissionResolver
     *                                                  (herencia de grupos). La sesión los combina
     *                                                  con los overrides personales.
     */
    public function applyResolved(array $resolvedFromGroups): void{
        $final = $resolvedFromGroups;

        // Los nodos personales activos sobreescriben todo
        foreach($this->personalNodes as $node){
            if(!$node->isActive()){
                continue;
            }
            if(!$node->appliesIn($this->context)){
                continue;
            }

            $perm = $node->getPermission();
            if($node->isWildcard()){
                NodeRegistry::getInstance()->registerWildcardIfNeeded($perm);
            }

            $final[$perm] = $node->getState();
        }

        $this->resolvedPermissions = $final;
        $this->pushToAttachment();
    }

    /**
     * Verifica si el jugador tiene un permiso (consulta el cache resuelto).
     */
    public function hasPermission(string $permission): bool{
        return $this->resolvedPermissions[$permission] ?? false;
    }

    /**
     * @return array<string, bool>
     */
    public function getResolvedPermissions(): array{
        return $this->resolvedPermissions;
    }

    // ── Meta ─────────────────────────────────────────────────────────

    public function setMeta(string $key, string $value): void{
        $this->meta[$key] = $value;
        $this->dirty = true;
    }

    public function unsetMeta(string $key): void{
        unset($this->meta[$key]);
        $this->dirty = true;
    }

    public function getMeta(string $key): ?string{
        return $this->meta[$key] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function getAllMeta(): array{
        return $this->meta;
    }

    // ── Prefix / Suffix ──────────────────────────────────────────────

    public function setPrefix(string $prefix): void{
        $this->prefix = $prefix;
    }

    public function setSuffix(string $suffix): void{
        $this->suffix = $suffix;
    }

    public function getPrefix(): string{
        return $this->prefix;
    }

    public function getSuffix(): string{
        return $this->suffix;
    }

    // ── Estado ───────────────────────────────────────────────────────

    public function isDirty(): bool{
        return $this->dirty;
    }

    public function markClean(): void{
        $this->dirty = false;
    }

    public function getPlayer(): Player{
        return $this->player;
    }

    public function getXuid(): string{
        return $this->player->getXuid();
    }

    public function getUsername(): string{
        return $this->player->getName();
    }

    public function setGroups(array $groups): void{
        $this->groups = $groups;
        $this->dirty = true;
        $this->recalculate();
    }

    /**
     * @param Node[] $nodes
     */
    public function setPersonalNodes(array $nodes): void{
        $this->personalNodes = $nodes;
        $this->dirty = true;
        $this->recalculate();
    }

    public function setMetaMap(array $meta): void{
        $this->meta = $meta;
        $this->dirty = true;
    }

    // ── Interno ──────────────────────────────────────────────────────

    /**
     * Callback que ejecuta SessionManager::refresh() sobre esta sesión.
     * Se inyecta desde SessionManager::open() para evitar dependencia circular.
     * @var \Closure(PlayerSession): void
     */
    private ?\Closure $refreshCallback = null;

    public function setRefreshCallback(\Closure $callback): void {
        $this->refreshCallback = $callback;
    }

    private function recalculate(): void {
        if ($this->refreshCallback === null) {
            // Sesión todavía inicializándose — SessionManager hará el
            // primer refresh manualmente al final de open()
            return;
        }

        ($this->refreshCallback)($this);
    }

    private function pushToAttachment(): void{
        $this->attachment->clearPermissions();

        foreach($this->resolvedPermissions as $permission => $state){
            $this->attachment->setPermission($permission, $state);
        }
    }
}