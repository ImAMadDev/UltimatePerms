<?php

declare(strict_types=1);

namespace appgallery\uperms\command;

use appgallery\uperms\util\DurationParser;
use appgallery\uperms\locale\LocaleManager;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

abstract class SubCommand{

    abstract public function getName(): string;

    abstract public function getDescription(): string;

    abstract public function getPermission(): string;

    abstract public function execute(CommandSender $sender, array $args): void;

    /** @return string[] */
    public function getAliases(): array{
        return [];
    }

    /** @return string[] */
    public function onTabComplete(CommandSender $sender, array $args): array{
        return [];
    }

    // ── Helpers ───────────────────────────────────────────────────────

    protected function requirePlayer(CommandSender $sender): bool{
        if(!($sender instanceof Player)){
            $sender->sendMessage(LocaleManager::get('only-in-game'));
            return false;
        }
        return true;
    }

    protected function usage(CommandSender $sender, string $usageKey): void{
        $usage = LocaleManager::get($usageKey);
        $sender->sendMessage(LocaleManager::get('command-usage', ['usage' => $usage]));
    }

    protected function err(CommandSender $sender, string $msg): void{
        $sender->sendMessage(TextFormat::RED . $msg);
    }

    protected function ok(CommandSender $sender, string $msg): void{
        $sender->sendMessage(TextFormat::GREEN . $msg);
    }

    /** @return array<string, string> */
    protected function parseContext(array $parts): array{
        $ctx = [];
        foreach($parts as $part){
            if(str_contains($part, '=')){
                [$key, $value] = explode('=', $part, 2);
                $ctx[trim($key)] = trim($value);
            }
        }
        return $ctx;
    }

    /**
     * Parsea los argumentos de un "perm set" de forma consistente.
     *
     * Sintaxis:  <node> [true|false] [duration] [key=val ...]
     * Ejemplos:
     *   essentials.fly
     *   essentials.fly false
     *   essentials.fly true 1d
     *   essentials.fly false 7d world=pvp
     *   essentials.fly true world=spawn gamemode=survival
     *
     * @return array{
     *   node: string,
     *   state: bool,
     *   expiresAt: int|null,
     *   context: array<string,string>
     * }
     */
    protected function parsePermArgs(array $args): ?array{
        // args[0] = node  (requerido)
        if(empty($args[0])){
            return null;
        }

        $node = $args[0];
        $remaining = array_slice($args, 1);

        // ── state (opcional, default true) ───────────────────────────────
        $state = true;
        if(!empty($remaining) && in_array(strtolower($remaining[0]), ['true', 'false'], true)){
            $state = strtolower($remaining[0]) !== 'false';
            $remaining = array_slice($remaining, 1);
        }

        // ── duration (opcional, si no contiene '=' y no es vacío) ────────
        $expiresAt = null;
        if(!empty($remaining) && !str_contains($remaining[0], '=')){
            $expiresAt = DurationParser::toTimestamp($remaining[0]);
            $remaining = array_slice($remaining, 1);
        }

        // ── context (resto: key=value) ────────────────────────────────────
        $context = $this->parseContext($remaining);

        return compact('node', 'state', 'expiresAt', 'context');
    }
}