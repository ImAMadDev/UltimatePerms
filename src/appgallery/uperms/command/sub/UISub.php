<?php

declare(strict_types=1);

namespace appgallery\uperms\command\sub;

use appgallery\uperms\command\SubCommand;
use appgallery\uperms\locale\LocaleManager;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

final class UISub extends SubCommand{

    public function getName(): string{
        return 'ui';
    }

    public function getDescription(): string{
        return 'Open the admin UI';
    }

    public function getPermission(): string{
        return 'ultimateperms.ui';
    }

    public function execute(CommandSender $sender, array $args): void{
        if(!$this->requirePlayer($sender)){
            return;
        }

        // TODO: instanciar MainMenuForm cuando esté implementado
        $sender->sendMessage(LocaleManager::get('ui-coming-soon'));
    }
}