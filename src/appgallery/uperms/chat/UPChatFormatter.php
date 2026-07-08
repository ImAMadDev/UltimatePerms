<?php

declare(strict_types=1);

namespace appgallery\uperms\chat;

use appgallery\uperms\group\Group;
use appgallery\uperms\group\GroupManager;
use appgallery\uperms\player\PlayerSession;
use appgallery\uperms\player\SessionManager;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\lang\Translatable;
use pocketmine\player\chat\ChatFormatter;
use pocketmine\utils\TextFormat;

class UPChatFormatter implements ChatFormatter{
    public function __construct(
        private readonly GroupManager   $groupManager,
        private readonly SessionManager $sessionManager,
        private readonly string $chatTemplate,
        private readonly string $nameTemplate,
    ) {}

    /**
     * Formato del mensaje en el chat.
     * Default: {prefix}{name}{suffix}§r: {message}
     * Configurable desde config.yml → chat-format
     */
    public function formatMessage(PlayerSession $session, string $message): string {
        return $this->replacePlaceholders($session, $this->chatTemplate, $message);
    }

    /**
     * Resuelve el display name (nametag sobre la cabeza).
     * Default: {prefix}{name}{suffix}
     */
    public function formatDisplayName(PlayerSession $session): string {
        return $this->replacePlaceholders($session, $this->nameTemplate, '');
    }

    /**
     * Aplica el display name al jugador online.
     */
    public function applyDisplayName(PlayerSession $session): void {
        $name = $this->formatDisplayName($session);
        $name = rtrim($name, TextFormat::RESET);
        $session->getPlayer()->setDisplayName($name);
        $session->getPlayer()->setNameTag($name);
    }

    // ── Resolución de prefix / suffix ────────────────────────────────

    /**
     * Retorna el prefijo efectivo del jugador:
     * override personal > rango de mayor weight > herencia > vacío
     */
    public function resolvePrefix(PlayerSession $session): string {
        // Override personal
        $personal = $session->getPrefix();
        if ($personal !== '') {
            return $personal . ' ';
        }

        // Rango primario (mayor weight)
        $primary = $this->sessionManager->getPrimaryGroup($session);
        if ($primary !== null && $primary->getPrefix() !== '') {
            return $primary->getPrefix() . ' ';
        }

        // Herencia del rango primario
        if ($primary !== null) {
            $inherited = $this->resolveInheritedPrefix($primary);
            if ($inherited !== '') {
                return $inherited . ' ';
            }
        }

        return '';
    }

    /**
     * Retorna el sufijo efectivo del jugador con la misma lógica.
     */
    public function resolveSuffix(PlayerSession $session): string {
        $personal = $session->getSuffix();
        if ($personal !== '') {
            return $personal . ' ';
        }

        $primary = $this->sessionManager->getPrimaryGroup($session);
        if ($primary !== null && $primary->getSuffix() !== '') {
            return $primary->getSuffix() . ' ';
        }

        if ($primary !== null) {
            $inherited = $this->resolveInheritedSuffix($primary);
            if ($inherited !== '') {
                return $inherited . ' ';
            }
        }

        return '';
    }

    /**
     * Retorna el color del chat (meta: chat-color) o blanco por defecto.
     */
    public function resolveChatColor(PlayerSession $session): string {
        // Personal primero
        $color = $session->getMeta('chat-color');
        if ($color !== null) {
            return $color;
        }

        // Del rango primario
        $primary = $this->sessionManager->getPrimaryGroup($session);
        if ($primary !== null) {
            $color = $primary->getMetaValue('chat-color');
            if ($color !== null) {
                return $color;
            }
        }

        return TextFormat::WHITE;
    }

    // ── Placeholders ─────────────────────────────────────────────────

    /**
     * Reemplaza todos los placeholders en el template.
     *
     * Disponibles:
     *   {prefix}     → prefijo resuelto
     *   {suffix}     → sufijo resuelto
     *   {name}       → nombre del jugador
     *   {displayname}→ display name actual
     *   {message}    → mensaje del chat
     *   {group}      → ID del rango primario
     *   {group_display} → displayName del rango primario
     *   {chat_color} → color del mensaje (meta chat-color)
     */
    private function replacePlaceholders(PlayerSession $session, string $template, string $message): string {
        $primary = $this->sessionManager->getPrimaryGroup($session);

        $placeholders = [
            '{prefix}'        => $this->resolvePrefix($session),
            '{suffix}'        => $this->resolveSuffix($session),
            '{name}'          => $session->getUsername(),
            '{displayname}'   => $session->getPlayer()->getDisplayName(),
            '{message}'       => $this->resolveChatColor($session) . $message,
            '{group}'         => $primary?->getId()          ?? 'default',
            '{group_display}' => $primary?->getDisplayName() ?? 'Default',
            '{chat_color}'    => $this->resolveChatColor($session),
        ];

        return str_replace(
                array_keys($placeholders),
                array_values($placeholders),
                $template
            ) . TextFormat::RESET;
    }

    // ── Herencia de prefix/suffix ─────────────────────────────────────

    private function resolveInheritedPrefix(Group $group, array &$visited = []): string {
        if (in_array($group->getId(), $visited, true)) {
            return '';
        }
        $visited[] = $group->getId();

        if ($group->getParent() !== null) {
            $parent = $this->groupManager->get($group->getParent());
            if ($parent === null) {
                return '';
            }

            if ($parent->getPrefix() !== '') {
                return $parent->getPrefix();
            }

            $inherited = $this->resolveInheritedPrefix($parent, $visited);
            if ($inherited !== '') {
                return $inherited;
            }
        }

        return '';
    }

    private function resolveInheritedSuffix(Group $group, array &$visited = []): string {
        if (in_array($group->getId(), $visited, true)) {
            return '';
        }
        $visited[] = $group->getId();

        if ($group->getParent() !== null) {
            $parent = $this->groupManager->get($group->getParent());
            if ($parent === null) {
                return '';
            }

            if ($parent->getSuffix() !== '') {
                return $parent->getSuffix();
            }

            $inherited = $this->resolveInheritedSuffix($parent, $visited);
            if ($inherited !== '') {
                return $inherited;
            }
        }

        return '';
    }

    private function getSessionFromFormattedName(string $formattedName): ?PlayerSession {
        $cleaned = TextFormat::clean($formattedName);
        $cleaned = preg_replace('/&[0-9a-fk-or]/i', '', $cleaned);

        $bestMatch = null;
        $bestLen = 0;

        foreach ($this->sessionManager->getAll() as $session) {
            $name = $session->getUsername();
            if (stripos($cleaned, $name) !== false) {
                $len = strlen($name);
                if ($len > $bestLen) {
                    $bestLen = $len;
                    $bestMatch = $session;
                }
            }
        }

        return $bestMatch;
    }

    public function format(string $username, string $message): Translatable|string{
        $message = TextFormat::clean($message);
        $session = $this->getSessionFromFormattedName($username);
        if($session === null) {
            return KnownTranslationFactory::chat_type_text($username, $message);
        }
        return $this->formatMessage($session, $message);
    }
}