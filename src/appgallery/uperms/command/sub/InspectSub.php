<?php

declare(strict_types=1);

namespace appgallery\uperms\command\sub;

use appgallery\uperms\command\SubCommand;
use appgallery\uperms\locale\LocaleManager;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

final class InspectSub extends SubCommand{

    public function getName(): string{
        return 'inspect';
    }

    public function getDescription(): string{
        return 'List all permissions registered by a plugin';
    }

    public function getPermission(): string{
        return 'ultimateperms.command.inspect';
    }

    // /uperms inspect <pluginName>
    public function execute(CommandSender $sender, array $args): void{
        if(empty($args)){
            $this->usage($sender, 'usage-inspect');
            return;
        }

        $pluginName = $args[0];
        $plugin = Server::getInstance()->getPluginManager()->getPlugin($pluginName);

        if($plugin === null){
            $sender->sendMessage(LocaleManager::get('plugin-not-found', ['plugin' => $pluginName]));
            return;
        }

        $permissions = $plugin->getDescription()->getPermissions();

        if(empty($permissions)){
            $sender->sendMessage(LocaleManager::get('plugin-no-perms', ['plugin' => $pluginName]));
            return;
        }

        $sender->sendMessage(LocaleManager::get('plugin-perms-header', [
            'plugin' => $pluginName,
            'count' => (string)count($permissions)
        ]));

        foreach($permissions as $perms){
            foreach($perms as $perm){


                $has = $sender instanceof Player
                    ? $sender->hasPermission($perm->getName())
                    : true;

                $prefix = $has ? TextFormat::GREEN . "  ✓ " : TextFormat::RED . "  ✗ ";
                $desc = $perm->getDescription() ?: LocaleManager::get('no-description');

                $sender->sendMessage(
                    $prefix . TextFormat::WHITE . $perm->getName() .
                    TextFormat::GRAY . " — " . $desc
                );
            }
        }
    }

    public function onTabComplete(CommandSender $sender, array $args): array{
        if(count($args) === 1){
            return array_filter(
                array_map(fn($p) => $p->getName(), Server::getInstance()->getPluginManager()->getPlugins()),
                fn($n) => str_starts_with(strtolower($n), strtolower($args[0]))
            );
        }
        return [];
    }
}