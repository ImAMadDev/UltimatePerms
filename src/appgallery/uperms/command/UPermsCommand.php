<?php

declare(strict_types=1);

namespace appgallery\uperms\command;

use appgallery\uperms\command\sub\group\GroupSub;
use appgallery\uperms\command\sub\CheckSub;
use appgallery\uperms\command\sub\InspectSub;
use appgallery\uperms\command\sub\ReloadSub;
use appgallery\uperms\command\sub\SimulateSub;
use appgallery\uperms\command\sub\UISub;
use appgallery\uperms\command\sub\user\UserSub;
use appgallery\uperms\group\GroupManager;
use appgallery\uperms\player\SessionManager;
use appgallery\uperms\locale\LocaleManager;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

final class UPermsCommand extends Command{

    /** @var array<string, SubCommand> */
    private array $subCommands = [];

    public function __construct(
        private readonly GroupManager   $groupManager,
        private readonly SessionManager $sessionManager,
    ){
        parent::__construct(
            name: 'uperms',
            description: 'UltimatePerms main command',
            usageMessage: '/uperms <subcommand>',
            aliases: ['up', 'perms']
        );

        $this->setPermission('ultimateperms.command');
        $this->registerSubCommands();
    }

    private function registerSubCommands(): void{
        $subs = [
            // user
            new UserSub($this->groupManager, $this->sessionManager),
            // group
            new GroupSub($this->groupManager, $this->sessionManager),
            // misc
            new CheckSub($this->groupManager, $this->sessionManager),
            new SimulateSub($this->groupManager, $this->sessionManager),
            new InspectSub(),
            new ReloadSub($this->groupManager, $this->sessionManager),
            new UISub(),
        ];

        foreach($subs as $sub){
            $this->subCommands[$sub->getName()] = $sub;

            foreach($sub->getAliases() as $alias){
                $this->subCommands[$alias] = $sub;
            }
        }
    }

    public function execute(CommandSender $sender, string $label, array $args): void{
        if(!$this->testPermission($sender)){
            return;
        }

        if(empty($args)){
            $this->sendHelp($sender);
            return;
        }

        $subName = strtolower(array_shift($args));
        $sub = $this->subCommands[$subName] ?? null;

        if($sub === null){
            $sender->sendMessage(LocaleManager::get('unknown-subcommand', ['sub' => $subName]));
            return;
        }

        if(!$sender->hasPermission($sub->getPermission())){
            $sender->sendMessage(LocaleManager::get('no-permission'));
            return;
        }

        $sub->execute($sender, $args);
    }

    public function onTabComplete(CommandSender $sender, string $alias, array $args): array{
        if(count($args) === 1){
            $unique = array_unique(
                array_map(fn(SubCommand $s) => $s->getName(), array_values($this->subCommands))
            );
            return array_filter($unique, fn(string $n) => str_starts_with($n, strtolower($args[0])));
        }

        $sub = $this->subCommands[strtolower($args[0])] ?? null;
        if($sub === null){
            return [];
        }

        return $sub->onTabComplete($sender, array_slice($args, 1));
    }

    private function sendHelp(CommandSender $sender): void{
        $sender->sendMessage(LocaleManager::get('command-help-header'));

        $shown = [];
        foreach($this->subCommands as $sub){
            if(in_array($sub->getName(), $shown, true)){
                continue;
            }

            if(!$sender->hasPermission($sub->getPermission())){
                continue;
            }

            $sender->sendMessage(LocaleManager::get('command-help-item', [
                'sub' => $sub->getName(),
                'desc' => $sub->getDescription()
            ]));
            $shown[] = $sub->getName();
        }
    }
}