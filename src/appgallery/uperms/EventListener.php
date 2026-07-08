<?php

declare(strict_types=1);

namespace appgallery\uperms;

use appgallery\uperms\chat\UPChatFormatter;
use appgallery\uperms\player\SessionManager;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerGameModeChangeEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;

final class EventListener implements Listener{

    public function __construct(
        private readonly SessionManager  $sessionManager,
        private readonly UPChatFormatter $chatFormatter,
    ){
    }

    public function onJoin(PlayerJoinEvent $event): void{
        $this->sessionManager->open($event->getPlayer());
    }

    public function onQuit(PlayerQuitEvent $event): void{
        $this->sessionManager->close($event->getPlayer());
    }

    public function onWorldChange(EntityTeleportEvent $event): void{
        $player = $event->getEntity();
        if(!$player instanceof Player){
            return;
        }

        if($event->getFrom()->getWorld() === $event->getTo()->getWorld()){
            return;
        }

        $this->sessionManager->updateContext(
            $player,
            'world',
            $player->getWorld()->getFolderName()
        );
    }

    public function onGamemodeChange(PlayerGameModeChangeEvent $event): void{
        $this->sessionManager->updateContext(
            $event->getPlayer(),
            'gamemode',
            mb_strtolower($event->getNewGamemode()->name)
        );
    }

    public function onChat(PlayerChatEvent $event): void{
        $player = $event->getPlayer();
        $session = $this->sessionManager->get($player->getXuid());

        if($session === null){
            return;
        }

        $event->setFormatter($this->chatFormatter);
    }
}