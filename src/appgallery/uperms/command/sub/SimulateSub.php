<?php

declare(strict_types=1);

namespace appgallery\uperms\command\sub;

use appgallery\uperms\command\SubCommand;
use appgallery\uperms\group\GroupManager;
use appgallery\uperms\permission\PermissionResolver;
use appgallery\uperms\player\SessionManager;
use appgallery\uperms\locale\LocaleManager;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

final class SimulateSub extends SubCommand{

    public function __construct(
        private readonly GroupManager   $groupManager,
        private readonly SessionManager $sessionManager,
    ){
    }

    public function getName(): string{
        return 'simulate';
    }

    public function getDescription(): string{
        return 'Simulate a permission check without applying it';
    }

    public function getPermission(): string{
        return 'ultimateperms.command.simulate';
    }

    public function getAliases(): array{
        return ['sim'];
    }

    // /uperms simulate <player> <permission> [ctx=val...]
    public function execute(CommandSender $sender, array $args): void{
        if(count($args) < 2){
            $this->usage($sender, 'usage-simulate');
            return;
        }

        $targetName = $args[0];
        $permission = $args[1];
        $session = $this->sessionManager->getByName($targetName);

        if($session === null){
            $sender->sendMessage(LocaleManager::get('player-not-online', ['player' => $targetName]));
            return;
        }

        // Contexto de simulación: puede sobreescribir el actual
        $context = $session->getContext();
        foreach(array_slice($args, 2) as $part){
            if(str_contains($part, '=')){
                [$k, $v] = explode('=', $part, 2);
                $context[$k] = $v;
            }
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
            context: $context,
        );

        $ctxStr = '';
        if(!empty($context)){
            $ctxPairs = implode(', ', array_map(fn($k, $v) => "{$k}={$v}", array_keys($context), $context));
            $ctxStr = LocaleManager::get('trace-simulate-context', ['context' => $ctxPairs]);
        }

        $sender->sendMessage(LocaleManager::get('trace-simulate-format', [
            'player' => $targetName,
            'permission' => $permission,
            'trace' => $trace->describe(),
            'context' => $ctxStr
        ]));
    }
}