<?php

declare(strict_types=1);

namespace appgallery\uperms\player;

use appgallery\uperms\chat\ChatFormatter;
use appgallery\uperms\chat\UPChatFormatter;
use appgallery\uperms\group\Group;
use appgallery\uperms\group\GroupManager;
use appgallery\uperms\Loader;
use appgallery\uperms\permission\node\Node;
use appgallery\uperms\permission\PermissionResolver;
use appgallery\uperms\player\context\ContextManager;
use appgallery\uperms\storage\IStorage;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\TextFormat;

final class SessionManager{
    use SingletonTrait;

    /** @var array<string, PlayerSession>  xuid → session */
    private array $sessions = [];

    private IStorage $storage;
    private GroupManager $groupManager;
    private PermissionResolver $resolver;
    private ContextManager $contextManager;
    private ?UPChatFormatter $chatFormatter = null;
    private string $nameTemplate = '{prefix}{name}{suffix}';


    public function init(
        IStorage           $storage,
        GroupManager       $groupManager,
        PermissionResolver $resolver,
        ContextManager     $contextManager,
    ): void{
        $this->storage = $storage;
        $this->groupManager = $groupManager;
        $this->resolver = $resolver;
        $this->contextManager = $contextManager;
    }

    // ── Ciclo de vida de sesión ───────────────────────────────────────

    /**
     * Crea y carga la sesión al conectarse el jugador.
     * Llamado en PlayerJoinEvent.
     */
    public function open(Player $player): void{
        $xuid = $player->getXuid();
        $attachment = $player->addAttachment(Loader::getInstance());

        $session = new PlayerSession($player, $attachment);

        // ── Inyectar callback ANTES de loadFrom para que recalculate()
        //    no dispare durante la carga inicial (el callback ya está listo
        //    pero la sesión aún no tiene grupos cargados)
        $session->setRefreshCallback(
            fn(PlayerSession $s) => $this->refresh($s)
        );

        Loader::getInstance()->getStorageManager()->loadPlayerAsync($xuid, function($data) use ($xuid, $player, $session){
            if($data !== null){
                PlayerSerializer::deserializeInto($session, $data);
            } else{
                // Jugador nuevo — asignar rango default
                $session->addGroup($this->groupManager->getDefaultGroupId());
                $session->markClean(); // addGroup marca dirty, pero es nuevo así que reseteamos
            }

            // Calcular contexto inicial (mundo, gamemode)
            $context = $this->contextManager->calculate($player);
            $session->setContext($context); // setContext llama recalculate internamente

            // Resolver permisos y aplicar al attachment
            $this->refresh($session);

            $this->sessions[$xuid] = $session;

            \GlobalLogger::get()->info(
                TextFormat::GRAY . "[UltimatePerms] Session opened for {$player->getName()} ({$xuid})"
            );
            $this->chatFormatter->applyDisplayName($session);
        });
    }

    /**
     * Persiste y destruye la sesión al desconectarse.
     * Llamado en PlayerQuitEvent.
     */
    public function close(Player $player): void{
        $xuid = $player->getXuid();
        $session = $this->sessions[$xuid] ?? null;

        if($session === null){
            return;
        }

        if($session->isDirty()){
            Loader::getInstance()->getStorageManager()->savePlayerAsync(PlayerSerializer::serialize($session), function() use ($session){
                $session->markClean();
            });
        }

        unset($this->sessions[$xuid]);

        \GlobalLogger::get()->info(
            TextFormat::GRAY . "[UltimatePerms] Session closed for {$player->getName()} ({$xuid})"
        );
    }

    /**
     * Persiste todas las sesiones abiertas sin destruirlas.
     * Llamado en onDisable() para no perder datos.
     */
    public function saveAll(): void{
        foreach($this->sessions as $session){
            if($session->isDirty()){
                $this->storage->savePlayer(PlayerSerializer::serialize($session));
                $session->markClean();
            }
        }
    }

    // ── Resolución de permisos ────────────────────────────────────────

    /**
     * Recalcula los permisos efectivos de una sesión y los aplica al attachment.
     * Llamado al: join, cambio de mundo/gamemode, cambio de rango/nodo.
     */
    public function refresh(PlayerSession $session): void{
        $context = $session->getContext();

        // 1. Obtener grupos del jugador ordenados por weight DESC
        $groups = [];
        foreach(array_keys($session->getGroups()) as $groupId){
            $group = $this->groupManager->get($groupId);
            if($group !== null){
                $groups[] = $group;
            }
        }

        // 2. Resolver permisos de grupos (herencia incluida)
        $resolvedFromGroups = $this->resolver->resolveGroups(
            $groups,
            $this->groupManager->getRegistry(),
            $context
        );

        // 3. Aplicar overrides personales y pushear al attachment
        $session->applyResolved($resolvedFromGroups);
        $this->chatFormatter?->applyDisplayName($session, $this->nameTemplate);
    }

    /**
     * Recalcula la sesión de todos los jugadores que tienen el grupo dado.
     * Llamado cuando se modifica un grupo (addNode, removeNode, etc).
     */
    public function refreshByGroup(string $groupId): void{
        foreach($this->sessions as $session){
            if($session->hasGroup($groupId)){
                $this->refresh($session);
            }
        }
    }

    /**
     * Recalcula todas las sesiones activas.
     * Usado en /uperms reload.
     */
    public function refreshAll(): void{
        foreach($this->sessions as $session){
            $this->refresh($session);
        }
    }

    public function refreshDisplayName(PlayerSession $session): void{
        $this->chatFormatter?->applyDisplayName($session);
    }

    public function setChatFormatter(UPChatFormatter $formatter, string $nameTemplate): void{
        $this->chatFormatter = $formatter;
        $this->nameTemplate = $nameTemplate;
    }

    // ── Contexto ─────────────────────────────────────────────────────

    /**
     * Actualiza una clave de contexto y recalcula si cambió.
     * Llamado en PlayerChangedWorldEvent / PlayerGameModeChangeEvent.
     */
    public function updateContext(Player $player, string $key, string $value): void{
        $session = $this->get($player->getXuid());
        if($session === null){
            return;
        }

        $previous = $session->getContext()[$key] ?? null;
        if($previous === $value){
            return; // Sin cambio real — no recalcular
        }

        $session->updateContext($key, $value);
        $this->refresh($session);
    }

    // ── Operaciones sobre sesiones ────────────────────────────────────

    /**
     * Asigna un rango al jugador, eliminando los demás rangos excepto el default.
     */
    public function setGroup(Player $player, string $groupId, ?int $expiresAt = null): void{
        $session = $this->getOrFail($player->getXuid());
        $defaultGroupId = $this->groupManager->getDefaultGroupId();

        $groups = $session->getGroups();
        $newGroups = [];

        if(array_key_exists($defaultGroupId, $groups)){
            $newGroups[$defaultGroupId] = $groups[$defaultGroupId];
        }

        $newGroups[$groupId] = $expiresAt;

        $session->setGroups($newGroups);
        $this->storage->savePlayer(PlayerSerializer::serialize($session));
        $session->markClean();
        $this->refresh($session);
    }

    /**
     * Añade un rango adicional (multi-rango).
     */
    public function addGroup(Player $player, string $groupId, ?int $expiresAt = null): void{
        $session = $this->getOrFail($player->getXuid());
        $session->addGroup($groupId, $expiresAt);
        $this->storage->savePlayer(PlayerSerializer::serialize($session));
        $session->markClean();
        $this->refresh($session);
    }

    public function removeGroup(Player $player, string $groupId): void{
        $session = $this->getOrFail($player->getXuid());
        $session->removeGroup($groupId);

        // Si se quedó sin rangos, asignar default
        if(empty($session->getGroups())){
            $session->addGroup($this->groupManager->getDefaultGroupId());
        }

        $this->storage->savePlayer(PlayerSerializer::serialize($session));
        $session->markClean();
        $this->refresh($session);
    }

    public function addNode(Player $player, Node $node): void{
        $session = $this->getOrFail($player->getXuid());
        $session->addNode($node);
        $this->storage->savePlayer(PlayerSerializer::serialize($session));
        $session->markClean();
        $this->refresh($session);
    }

    public function removeNode(Player $player, string $permission): void{
        $session = $this->getOrFail($player->getXuid());
        $session->removeNode($permission);
        $this->storage->savePlayer(PlayerSerializer::serialize($session));
        $session->markClean();
        $this->refresh($session);
    }

    // ── Getters ───────────────────────────────────────────────────────

    public function get(string $xuid): ?PlayerSession{
        return $this->sessions[$xuid] ?? null;
    }

    public function getByName(string $username): ?PlayerSession{
        foreach($this->sessions as $session){
            if(strcasecmp($session->getUsername(), $username) === 0){
                return $session;
            }
        }
        return null;
    }

    /**
     * @return PlayerSession[]
     */
    public function getAll(): array{
        return array_values($this->sessions);
    }

    /**
     * Retorna el grupo de mayor weight entre los rangos activos del jugador.
     * Es el "rango primario" — el que define el prefijo visible y la jerarquía.
     */
    public function getPrimaryGroup(PlayerSession $session): ?Group{
        $primary = null;
        $topWeight = -1;

        foreach(array_keys($session->getGroups()) as $groupId){
            $group = $this->groupManager->get($groupId);

            if($group === null){
                continue;
            }

            if($group->getWeight() > $topWeight){
                $topWeight = $group->getWeight();
                $primary = $group;
            }
        }

        return $primary;
    }

    public function isOnline(string $xuid): bool{
        return isset($this->sessions[$xuid]);
    }

    public function count(): int{
        return count($this->sessions);
    }

    // ── Interno ───────────────────────────────────────────────────────

    private function getOrFail(string $xuid): PlayerSession{
        return $this->sessions[$xuid]
            ?? throw new \RuntimeException(
                "[UltimatePerms] No session found for xuid '$xuid'. " .
                "Is the player online?"
            );
    }
}