<?php

declare(strict_types=1);

namespace appgallery\uperms\command\sub;

use appgallery\uperms\command\SubCommand;
use appgallery\uperms\group\GroupManager;
use appgallery\uperms\Loader;
use appgallery\uperms\player\SessionManager;
use appgallery\uperms\locale\LocaleManager;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

final class ReloadSub extends SubCommand{

    public function __construct(
        private readonly GroupManager   $groupManager,
        private readonly SessionManager $sessionManager,
    ){
    }

    public function getName(): string{
        return 'reload';
    }

    public function getDescription(): string{
        return 'Reload config and messages';
    }

    public function getPermission(): string{
        return 'ultimateperms.command.reload';
    }

    public function execute(CommandSender $sender, array $args): void{
        $start = microtime(true);

        Loader::getInstance()->reloadConfig();
        LocaleManager::init(Loader::getInstance()->getConfig()->get('locale', 'en_US'));
        $this->sessionManager->refreshAll();

        $ms = round((microtime(true) - $start) * 1000, 2);
        $sender->sendMessage(LocaleManager::get('reload-success', ['ms' => (string)$ms]));
    }
}