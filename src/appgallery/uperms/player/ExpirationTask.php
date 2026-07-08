<?php

declare(strict_types=1);

namespace appgallery\uperms\player;

use appgallery\uperms\event\GroupPermExpireEvent;
use appgallery\uperms\event\PermissionExpireEvent;
use appgallery\uperms\event\GroupExpireEvent;
use appgallery\uperms\group\GroupManager;
use appgallery\uperms\storage\IStorage;
use appgallery\uperms\locale\LocaleManager;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat;

final class ExpirationTask extends Task{

    public function __construct(
        private readonly SessionManager $sessionManager,
        private readonly GroupManager   $groupManager,
        private readonly IStorage       $storage,
    ){
    }

    public function onRun(): void{
        $this->processPlayers();
        $this->processGroups();
    }

    // ── Jugadores ─────────────────────────────────────────────────────

    private function processPlayers(): void{
        foreach($this->sessionManager->getAll() as $session){
            $player = $session->getPlayer();
            $changed = false;

            // ── Rangos expirados ──────────────────────────────────────
            $expiredGroups = $session->flushExpiredGroups();

            foreach($expiredGroups as $groupId){
                (new GroupExpireEvent($player, $groupId))->call();

                \GlobalLogger::get()->info(
                    TextFormat::YELLOW .
                    "[UltimatePerms] Group '{$groupId}' expired for {$player->getName()}."
                );

                $changed = true;
            }

            // Sin rangos tras las expiraciones → asignar default
            if(!empty($expiredGroups) && empty($session->getGroups())){
                $session->addGroup($this->groupManager->getDefaultGroupId());
            }

            // ── Nodos personales expirados ────────────────────────────
            $expiredNodes = $session->flushExpiredNodes();

            foreach($expiredNodes as $node){
                (new PermissionExpireEvent($player, $node))->call();

                \GlobalLogger::get()->info(
                    TextFormat::YELLOW .
                    "[UltimatePerms] Permission '{$node->getPermission()}' expired for {$player->getName()}."
                );

                $changed = true;
            }

            // ── Persistir y refrescar si hubo cambios ─────────────────
            if($changed){
                $this->storage->savePlayer(PlayerSerializer::serialize($session));
                $session->markClean();
                $this->sessionManager->refresh($session);

                // Notificar al jugador
                if(!empty($expiredGroups)){
                    foreach($expiredGroups as $groupId){
                        $group = $this->groupManager->get($groupId);
                        $player->sendMessage(
                            LocaleManager::get('group-expired', [
                                'group' => $group?->getDisplayName() ?? $groupId,
                            ])
                        );
                    }
                }

                if(!empty($expiredNodes)){
                    foreach($expiredNodes as $node){
                        $player->sendMessage(
                            LocaleManager::get('perm-expired', [
                                'permission' => $node->getPermission(),
                            ])
                        );
                    }
                }
            }
        }
    }

    // ── Grupos ────────────────────────────────────────────────────────

    private function processGroups(): void{
        foreach($this->groupManager->getRegistry()->getAll() as $group){
            // flushExpiredNodes() ya existe en Group — devuelve Node[] expirados
            $expiredNodes = $group->flushExpiredNodes();

            if(empty($expiredNodes)){
                continue;
            }

            // Persistir el grupo sin los nodos expirados
            $this->groupManager->save($group);

            // Refrescar todos los jugadores que tienen este grupo
            $this->sessionManager->refreshByGroup($group->getId());

            // Disparar eventos y loggear
            foreach($expiredNodes as $node){
                (new GroupPermExpireEvent($group, $node))->call();

                \GlobalLogger::get()->info(
                    TextFormat::YELLOW .
                    "[UltimatePerms] Permission '{$node->getPermission()}' expired " .
                    "on group '{$group->getId()}'."
                );

                // Notificar a admins online
                $this->notifyAdmins(
                    LocaleManager::get('group-perm-expired', [
                        'permission' => $node->getPermission(),
                        'group' => $group->getId()
                    ])
                );
            }
        }
    }

    // ── Utilidades ────────────────────────────────────────────────────

    private function notifyAdmins(string $message): void{
        foreach($this->sessionManager->getAll() as $session){
            if($session->getPlayer()->hasPermission('ultimateperms.notify.expire')){
                $session->getPlayer()->sendMessage($message);
            }
        }
    }
}