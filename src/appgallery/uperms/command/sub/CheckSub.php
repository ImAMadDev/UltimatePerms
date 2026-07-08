<?php

declare(strict_types=1);

namespace appgallery\uperms\command\sub;

use appgallery\uperms\command\SubCommand;
use appgallery\uperms\group\GroupManager;
use appgallery\uperms\permission\PermissionResolver;
use appgallery\uperms\player\SessionManager;
use appgallery\uperms\locale\LocaleManager;
use pocketmine\command\CommandSender;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

final class CheckSub extends SubCommand{

    public function __construct(
        private readonly GroupManager   $groupManager,
        private readonly SessionManager $sessionManager,
    ){
    }

    public function getName(): string{
        return 'check';
    }

    public function getDescription(): string{
        return 'Check where a permission comes from';
    }

    public function getPermission(): string{
        return 'ultimateperms.command.check';
    }

    // /uperms check <player> <permission>
    public function execute(CommandSender $sender, array $args): void{
        if(count($args) < 2){
            $this->usage($sender, 'usage-check');
            return;
        }

        $targetName = $args[0];
        $permission = $args[1];
        $session = $this->sessionManager->getByName($targetName);

        if($session === null){
            $sender->sendMessage(LocaleManager::get('player-not-online', ['player' => $targetName]));
            return;
        }

        $groups = [];
        foreach(array_keys($session->getGroups()) as $groupId){
            $group = $this->groupManager->get($groupId);
            if($group !== null) $groups[] = $group;
        }

        $resolver = new PermissionResolver();
        $trace = $resolver->trace(
            permission: $permission,
            personalNodes: $session->getPersonalNodes(),
            groups: $groups,
            registry: $this->groupManager->getRegistry(),
            context: $session->getContext(),
        );

        $sender->sendMessage(LocaleManager::get('trace-check-format', [
            'player' => $targetName,
            'permission' => $permission,
            'trace' => $trace->describe()
        ]));
    }

    public function onTabComplete(CommandSender $sender, array $args): array{
        return match (count($args)) {
            1 => array_map(fn($p) => $p->getName(), Server::getInstance()->getOnlinePlayers()),
            default => [],
        };
    }
}