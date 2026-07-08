<?php

declare(strict_types=1);

namespace appgallery\uperms\command\sub\user;

use appgallery\uperms\command\SubCommand;
use appgallery\uperms\group\GroupManager;
use appgallery\uperms\permission\node\Node;
use appgallery\uperms\permission\node\NodeRegistry;
use appgallery\uperms\permission\PermissionResolver;
use appgallery\uperms\player\PlayerSession;
use appgallery\uperms\player\SessionManager;
use appgallery\uperms\util\DurationParser;
use appgallery\uperms\locale\LocaleManager;
use pocketmine\command\CommandSender;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

final class UserSub extends SubCommand{

    public function __construct(
        private readonly GroupManager   $groupManager,
        private readonly SessionManager $sessionManager,
    ){
    }

    public function getName(): string{
        return 'user';
    }

    public function getAliases(): array{
        return ['u'];
    }

    public function getDescription(): string{
        return 'Manage players (groups, perms, meta)';
    }

    public function getPermission(): string{
        return 'ultimateperms.command.user';
    }

    /*
     * Routing:
     *   /uperms user <player> info
     *   /uperms user <player> group <set|add|remove> <group> [duration]
     *   /uperms user <player> perm <set|unset|check> <node> [true|false] [duration] [ctx=v]
     *   /uperms user <player> meta <set|unset|get> <key> [value]
     *   /uperms user <player> audit [page]
     */
    public function execute(CommandSender $sender, array $args): void{
        // args[0] = player, args[1] = action
        if(count($args) < 2){
            $this->sendHelp($sender);
            return;
        }

        $targetName = $args[0];
        $action = strtolower($args[1]);

        $session = $this->sessionManager->getByName($targetName);
        if($session === null){
            // Buscar en storage de forma offline
            $storage = \appgallery\uperms\Loader::getInstance()->getStorage();
            $data = $storage->loadPlayerByName($targetName);
            if($data === null){
                $sender->sendMessage(LocaleManager::get('player-not-found', ['player' => $targetName]));
                return;
            }
            $offlineData = \appgallery\uperms\player\OfflinePlayerData::fromStorage($data);

            // Procesar acción offline
            $success = match ($action) {
                'info'   => $this->infoOffline($sender, $offlineData),
                'group'  => $this->groupOffline($sender, $offlineData, array_slice($args, 2)),
                'perm'   => $this->permOffline($sender, $offlineData, array_slice($args, 2)),
                'meta'   => $this->metaOffline($sender, $offlineData, array_slice($args, 2)),
                'prefix' => $this->prefixOffline($sender, $offlineData, array_slice($args, 2)),
                'suffix' => $this->suffixOffline($sender, $offlineData, array_slice($args, 2)),
                'audit'  => $this->auditOffline($sender, $offlineData),
                default  => false,
            };

            if($success && $action !== 'info' && $action !== 'audit' && !($action === 'meta' && count($args) >= 3 && strtolower($args[2]) === 'get') && !($action === 'prefix' && count($args) >= 3 && strtolower($args[2]) === 'get') && !($action === 'suffix' && count($args) >= 3 && strtolower($args[2]) === 'get')){
                // Solo guardar si la acción modificó los datos y fue exitosa
                $storage->savePlayer($offlineData->serialize());
            }
            return;
        }

        match ($action) {
            'info'   => $this->info($sender, $session),
            'group'  => $this->group($sender, $session, $targetName, array_slice($args, 2)),
            'perm'   => $this->perm($sender, $session, $targetName, array_slice($args, 2)),
            'meta'   => $this->meta($sender, $session, $targetName, array_slice($args, 2)),
            'audit'  => $this->audit($sender, $session, $targetName, array_slice($args, 2)),
            'prefix' => $this->prefix($sender, $session, $targetName, array_slice($args, 2)),
            'suffix' => $this->suffix($sender, $session, $targetName, array_slice($args, 2)),
            default  => $this->sendHelp($sender),
        };
    }

    // ── info ──────────────────────────────────────────────────────────

    private function info(CommandSender $sender, PlayerSession $session): void{
        $sender->sendMessage(LocaleManager::get('user-info-header', ['player' => $session->getUsername()]));

        $sender->sendMessage(LocaleManager::get('user-info-groups'));
        foreach($session->getGroups() as $groupId => $expiresAt){
            $group = $this->groupManager->get($groupId);
            $name = $group?->getDisplayName() ?? $groupId;
            $expiry = $expiresAt !== null
                ? LocaleManager::get('user-info-group-expiry', ['duration' => DurationParser::format($expiresAt - time())])
                : LocaleManager::get('user-info-group-permanent');
            $sender->sendMessage(LocaleManager::get('user-info-group-format', [
                'group' => $name,
                'expiry' => $expiry
            ]));
        }

        $nodes = $session->getPersonalNodes();
        if(!empty($nodes)){
            $sender->sendMessage(LocaleManager::get('user-info-overrides'));
            foreach($nodes as $node){
                $icon = $node->getState() ? TextFormat::GREEN . "+" : TextFormat::RED . "-";
                $expiry = $node->isPermanent()
                    ? LocaleManager::get('user-info-override-permanent')
                    : LocaleManager::get('user-info-override-expiry', ['duration' => DurationParser::format($node->getExpiresAt() - time())]);
                $sender->sendMessage(LocaleManager::get('user-info-override-format', [
                    'state' => $icon,
                    'permission' => $node->getPermission(),
                    'expiry' => $expiry
                ]));
            }
        }

        $ctx = $session->getContext();
        $ctxPairs = implode(', ', array_map(fn($k, $v) => "{$k}={$v}", array_keys($ctx), $ctx));
        $sender->sendMessage(LocaleManager::get('user-info-context', [
            'context' => $ctxPairs
        ]));

        $prefixVal = $session->getPrefix() ?: LocaleManager::get('user-info-none');
        $suffixVal = $session->getSuffix() ?: LocaleManager::get('user-info-none');
        $sender->sendMessage(LocaleManager::get('user-info-prefix-suffix', [
            'prefix' => $prefixVal,
            'suffix' => $suffixVal
        ]));
    }

    // ── group ──────────────────────────────────────────────────────────

    private function group(CommandSender $sender, PlayerSession $session, string $targetName, array $args): void{
        // args: <set|add|remove> <group> [duration]
        if(count($args) < 2){
            $this->usage($sender, 'usage-user-group');
            return;
        }

        $action = strtolower($args[0]);
        $groupId = strtolower($args[1]);
        $duration = $args[2] ?? null;

        $group = $this->groupManager->get($groupId);
        if($group === null){
            $sender->sendMessage(LocaleManager::get('group-not-found', ['group' => $groupId]));
            return;
        }

        // Protección de jerarquía
        if(!$sender->hasPermission('ultimateperms.admin')){
            $senderSession = $this->sessionManager->getByName($sender->getName());
            if($senderSession !== null){
                $primary = $this->sessionManager->getPrimaryGroup($senderSession);
                if($primary !== null && $group->getWeight() >= $primary->getWeight()){
                    $sender->sendMessage(LocaleManager::get('hierarchy-denied'));
                    return;
                }
            }
        }

        $expiresAt = $duration !== null ? DurationParser::toTimestamp($duration) : null;
        $player = $session->getPlayer();

        switch($action){
            case 'set':
                $this->sessionManager->setGroup($player, $groupId, $expiresAt);
                $sender->sendMessage(LocaleManager::get('group-set', ['player' => $targetName, 'group' => $group->getDisplayName()]));
                break;
            case 'add':
                $this->sessionManager->addGroup($player, $groupId, $expiresAt);
                $sender->sendMessage(LocaleManager::get('group-added', ['player' => $targetName, 'group' => $group->getDisplayName()]));
                break;
            case 'remove':
                $this->sessionManager->removeGroup($player, $groupId);
                $sender->sendMessage(LocaleManager::get('group-removed', ['player' => $targetName, 'group' => $group->getDisplayName()]));
                break;
            default:
                $this->usage($sender, 'usage-user-group-invalid');
        }
    }

    // ── perm ──────────────────────────────────────────────────────────

    private function perm(
        CommandSender  $sender,
        PlayerSession  $session,
        string         $targetName,
        array          $args
    ): void{
        /*
         * args[0] = action: set | unset | check
         * args[1..] = argumentos de la acción
         *
         * SET:
         *   /uperms user Steve perm set essentials.fly
         *   /uperms user Steve perm set essentials.fly false
         *   /uperms user Steve perm set essentials.fly true 1d
         *   /uperms user Steve perm set essentials.fly false 7d world=pvp
         *
         * UNSET:
         *   /uperms user Steve perm unset essentials.fly
         *
         * CHECK:
         *   /uperms user Steve perm check essentials.fly
         */
        $action = strtolower($args[0] ?? '');

        if($action === '' || empty($args[1])){
            $this->usage($sender, 'usage-user-perm');
            return;
        }

        $player = $session->getPlayer();

        switch($action){

            case 'set':
                $parsed = $this->parsePermArgs(array_slice($args, 1));

                if($parsed === null){
                    $this->usage($sender, 'usage-user-perm-set');
                    return;
                }

                $node = new Node(
                    permission: $parsed['node'],
                    state: $parsed['state'],
                    expiresAt: $parsed['expiresAt'],
                    context: $parsed['context'],
                );

                $this->sessionManager->addNode($player, $node);

                // Feedback detallado
                $stateStr = $parsed['state'] ? TextFormat::GREEN . 'true' : TextFormat::RED . 'false';
                $expiryStr = $parsed['expiresAt'] !== null
                    ? LocaleManager::get('user-perm-set-expiry', ['duration' => DurationParser::format($parsed['expiresAt'] - time())])
                    : LocaleManager::get('user-perm-set-permanent');
                $ctxStr = '';
                if(!empty($parsed['context'])){
                    $ctxPairs = implode(', ', array_map(fn($k, $v) => "{$k}={$v}", array_keys($parsed['context']), $parsed['context']));
                    $ctxStr = LocaleManager::get('user-perm-set-context', ['context' => $ctxPairs]);
                }

                $sender->sendMessage(LocaleManager::get('user-perm-set', [
                    'permission' => $parsed['node'],
                    'state' => $stateStr,
                    'player' => $targetName,
                    'expiry' => $expiryStr,
                    'context' => $ctxStr
                ]));
                break;

            case 'unset':
                $permission = $args[1];
                $this->sessionManager->removeNode($player, $permission);
                $sender->sendMessage(LocaleManager::get('perm-unset', [
                    'permission' => $permission,
                    'player' => $targetName
                ]));
                break;

            case 'check':
                $permission = $args[1];
                $resolver = new PermissionResolver();
                $groups = [];

                foreach(array_keys($session->getGroups()) as $gid){
                    $g = $this->groupManager->get($gid);
                    if($g !== null){
                        $groups[] = $g;
                    }
                }

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
                break;

            default:
                $this->usage($sender, 'usage-user-perm-invalid');
        }
    }

    // ── meta ──────────────────────────────────────────────────────────

    private function meta(CommandSender $sender, PlayerSession $session, string $targetName, array $args): void{
        // args: <set|unset|get> <key> [value]
        if(count($args) < 2){
            $this->usage($sender, 'usage-user-meta');
            return;
        }

        $action = strtolower($args[0]);
        $key = $args[1];

        switch($action){
            case 'set':
                $value = $args[2] ?? '';
                $session->setMeta($key, $value);
                $sender->sendMessage(LocaleManager::get('meta-set', ['player' => $targetName, 'key' => $key, 'value' => $value]));
                break;
            case 'unset':
                $session->unsetMeta($key);
                $sender->sendMessage(LocaleManager::get('meta-unset', ['player' => $targetName, 'key' => $key]));
                break;
            case 'get':
                $value = $session->getMeta($key);
                $sender->sendMessage(LocaleManager::get('meta-get-format', [
                    'key' => $key,
                    'value' => $value !== null ? $value : LocaleManager::get('meta-get-not-set')
                ]));
                break;
            default:
                $this->usage($sender, 'usage-user-meta-invalid');
        }
    }

    // ── audit ─────────────────────────────────────────────────────────

    private function audit(CommandSender $sender, PlayerSession $session, string $targetName, array $args): void{
        $page = max(1, (int)($args[0] ?? 1));
        $all = $session->getResolvedPermissions();
        $chunks = array_chunk(array_keys($all), 10, true);
        $total = max(1, count($chunks));
        $page = min($page, $total);

        $sender->sendMessage(LocaleManager::get('user-audit-header', [
            'player' => $targetName,
            'page' => (string)$page,
            'total' => (string)$total
        ]));

        if(empty($all)){
            $sender->sendMessage(LocaleManager::get('user-audit-empty'));
            return;
        }

        foreach(array_keys(array_slice($all, ($page - 1) * 10, 10, true)) as $perm){
            $state = $all[$perm];
            $icon = $state ? TextFormat::GREEN . "  ✓ " : TextFormat::RED . "  ✗ ";
            $sender->sendMessage($icon . TextFormat::WHITE . $perm);
        }

        if($page < $total){
            $sender->sendMessage(LocaleManager::get('user-audit-next', [
                'player' => $targetName,
                'next' => (string)($page + 1)
            ]));
        }
    }

    // ── prefix ────────────────────────────────────────────────────────────

    private function prefix(
        CommandSender $sender,
        PlayerSession $session,
        string        $targetName,
        array         $args,
    ): void {
        /*
         * SET:   /uperms user Steve prefix set §6[VIP]
         * UNSET: /uperms user Steve prefix unset        ← vuelve al del rango
         * GET:   /uperms user Steve prefix get
         */
        $action = strtolower($args[0] ?? '');

        switch ($action) {
            case 'set':
                // El resto de args es el prefijo (puede tener espacios y §codes)
                $prefix = implode(' ', array_slice($args, 1));

                if ($prefix === '') {
                    $this->usage($sender, 'usage-user-prefix-set');
                    return;
                }

                $session->setPrefix($prefix);

                // Refrescar display name
                $this->sessionManager->refreshDisplayName($session);

                $sender->sendMessage(LocaleManager::get('user-prefix-set', [
                    'player' => $targetName,
                    'prefix' => $prefix
                ]));
                break;

            case 'unset':
                // null → el ChatFormatter usará el prefijo del rango
                $session->setPrefix('');
                $this->sessionManager->refreshDisplayName($session);

                $sender->sendMessage(LocaleManager::get('user-prefix-unset', [
                    'player' => $targetName
                ]));
                break;

            case 'get':
                $current = $session->getPrefix();
                $prefixVal = $current !== '' ? $current : LocaleManager::get('user-prefix-none');
                $sender->sendMessage(LocaleManager::get('user-prefix-get', [
                    'player' => $targetName,
                    'prefix' => $prefixVal
                ]));
                break;

            default:
                $this->usage($sender, 'usage-user-prefix');
        }
    }

// ── suffix ────────────────────────────────────────────────────────────

    private function suffix(
        CommandSender $sender,
        PlayerSession $session,
        string        $targetName,
        array         $args,
    ): void {
        /*
         * SET:   /uperms user Steve suffix set §c♦
         * UNSET: /uperms user Steve suffix unset
         * GET:   /uperms user Steve suffix get
         */
        $action = strtolower($args[0] ?? '');

        switch ($action) {
            case 'set':
                $suffix = implode(' ', array_slice($args, 1));

                if ($suffix === '') {
                    $this->usage($sender, 'usage-user-suffix-set');
                    return;
                }

                $session->setSuffix($suffix);
                $this->sessionManager->refreshDisplayName($session);

                $sender->sendMessage(LocaleManager::get('user-suffix-set', [
                    'player' => $targetName,
                    'suffix' => $suffix
                ]));
                break;

            case 'unset':
                $session->setSuffix('');
                $this->sessionManager->refreshDisplayName($session);

                $sender->sendMessage(LocaleManager::get('user-suffix-unset', [
                    'player' => $targetName
                ]));
                break;

            case 'get':
                $current = $session->getSuffix();
                $suffixVal = $current !== '' ? $current : LocaleManager::get('user-suffix-none');
                $sender->sendMessage(LocaleManager::get('user-suffix-get', [
                    'player' => $targetName,
                    'suffix' => $suffixVal
                ]));
                break;

            default:
                $this->usage($sender, 'usage-user-suffix');
        }
    }

    // ── help + tab ────────────────────────────────────────────────────

    private function sendHelp(CommandSender $sender): void{
        $sender->sendMessage(LocaleManager::get('user-help-header'));
        $sender->sendMessage(LocaleManager::get('user-help-info'));
        $sender->sendMessage(LocaleManager::get('user-help-group'));
        $sender->sendMessage(LocaleManager::get('user-help-perm'));
        $sender->sendMessage(LocaleManager::get('user-help-meta'));
        $sender->sendMessage(LocaleManager::get('user-help-audit'));
        $sender->sendMessage(LocaleManager::get('user-help-prefix'));
        $sender->sendMessage(LocaleManager::get('user-help-suffix'));
    }

    public function onTabComplete(CommandSender $sender, array $args): array{
        $players = array_map(fn($p) => $p->getName(), Server::getInstance()->getOnlinePlayers());
        $actions = ['info', 'group', 'perm', 'meta', 'audit'];

        return match (count($args)) {
            1 => array_filter($players, fn($p) => str_starts_with(strtolower($p), strtolower($args[0]))),
            2 => array_filter($actions, fn($a) => str_starts_with($a, strtolower($args[1]))),
            3 => match (strtolower($args[1])) {
                'group' => array_filter(['set', 'add', 'remove'], fn($s) => str_starts_with($s, $args[2])),
                'perm' => array_filter(['set', 'unset', 'check'], fn($s) => str_starts_with($s, $args[2])),
                'meta' => array_filter(['set', 'unset', 'get'], fn($s) => str_starts_with($s, $args[2])),
                default => [],
            },
            4 => match (strtolower($args[1])) {
                'group' => array_filter($this->groupManager->getAllIds(), fn($g) => str_starts_with($g, $args[3])),
                'perm' => array_filter(NodeRegistry::getInstance()->getAllPermissionNames(), fn($n) => str_starts_with($n, $args[3])),
                default => [],
            },
            5 => strtolower($args[1]) === 'perm'
                ? array_filter(['true', 'false'], fn($s) => str_starts_with($s, $args[4]))
                : [],
            default => [],
        };
    }

    private function groupOffline(CommandSender $sender, \appgallery\uperms\player\OfflinePlayerData $data, array $args): bool{
        if(count($args) < 2){
            $this->usage($sender, 'usage-user-group');
            return false;
        }

        $action = strtolower($args[0]);
        $groupId = strtolower($args[1]);
        $duration = $args[2] ?? null;

        $group = $this->groupManager->get($groupId);
        if($group === null){
            $sender->sendMessage(LocaleManager::get('group-not-found', ['group' => $groupId]));
            return false;
        }

        // Protección de jerarquía
        if(!$sender->hasPermission('ultimateperms.admin')){
            $senderSession = $this->sessionManager->getByName($sender->getName());
            if($senderSession !== null){
                $primary = $this->sessionManager->getPrimaryGroup($senderSession);
                if($primary !== null && $group->getWeight() >= $primary->getWeight()){
                    $sender->sendMessage(LocaleManager::get('hierarchy-denied'));
                    return false;
                }
            }
        }

        $expiresAt = $duration !== null ? DurationParser::toTimestamp($duration) : null;
        $targetName = $data->getUsername();

        switch($action){
            case 'set':
                $defaultGroupId = $this->groupManager->getDefaultGroupId();
                $groups = $data->getGroups();
                $newGroups = [];

                if(array_key_exists($defaultGroupId, $groups)){
                    $newGroups[$defaultGroupId] = $groups[$defaultGroupId];
                }
                $newGroups[$groupId] = $expiresAt;

                $data->setGroups($newGroups);
                $sender->sendMessage(LocaleManager::get('offline-group-set', ['player' => $targetName, 'group' => $group->getDisplayName()]));
                return true;
            case 'add':
                $data->addGroup($groupId, $expiresAt);
                $sender->sendMessage(LocaleManager::get('offline-group-added', ['player' => $targetName, 'group' => $group->getDisplayName()]));
                return true;
            case 'remove':
                $data->removeGroup($groupId);
                if(empty($data->getGroups())){
                    $data->addGroup($this->groupManager->getDefaultGroupId());
                }
                $sender->sendMessage(LocaleManager::get('offline-group-removed', ['player' => $targetName, 'group' => $group->getDisplayName()]));
                return true;
            default:
                $this->usage($sender, 'usage-user-group-invalid');
                return false;
        }
    }

    private function permOffline(CommandSender $sender, \appgallery\uperms\player\OfflinePlayerData $data, array $args): bool{
        $action = strtolower($args[0] ?? '');

        if($action === '' || empty($args[1])){
            $this->usage($sender, 'usage-user-perm');
            return false;
        }

        $targetName = $data->getUsername();

        switch($action){
            case 'set':
                $parsed = $this->parsePermArgs(array_slice($args, 1));

                if($parsed === null){
                    $this->usage($sender, 'usage-user-perm-set');
                    return false;
                }

                $node = new Node(
                    permission: $parsed['node'],
                    state: $parsed['state'],
                    expiresAt: $parsed['expiresAt'],
                    context: $parsed['context'],
                );

                $data->addNode($node);

                $stateStr = $parsed['state'] ? TextFormat::GREEN . 'true' : TextFormat::RED . 'false';
                $expiryStr = $parsed['expiresAt'] !== null
                    ? LocaleManager::get('user-perm-set-expiry', ['duration' => DurationParser::format($parsed['expiresAt'] - time())])
                    : LocaleManager::get('user-perm-set-permanent');
                $ctxStr = '';
                if(!empty($parsed['context'])){
                    $ctxPairs = implode(', ', array_map(fn($k, $v) => "{$k}={$v}", array_keys($parsed['context']), $parsed['context']));
                    $ctxStr = LocaleManager::get('user-perm-set-context', ['context' => $ctxPairs]);
                }

                $sender->sendMessage(LocaleManager::get('offline-perm-set', [
                    'permission' => $parsed['node'],
                    'state' => $stateStr,
                    'player' => $targetName,
                    'expiry' => $expiryStr,
                    'context' => $ctxStr
                ]));
                return true;

            case 'unset':
                $permission = $args[1];
                $data->removeNode($permission);
                $sender->sendMessage(LocaleManager::get('offline-perm-unset', [
                    'permission' => $permission,
                    'player' => $targetName
                ]));
                return true;

            case 'check':
                $sender->sendMessage(LocaleManager::get('offline-audit-unavailable'));
                return false;

            default:
                $this->usage($sender, 'usage-user-perm-invalid');
                return false;
        }
    }

    private function metaOffline(CommandSender $sender, \appgallery\uperms\player\OfflinePlayerData $data, array $args): bool{
        if(count($args) < 2){
            $this->usage($sender, 'usage-user-meta');
            return false;
        }

        $action = strtolower($args[0]);
        $key = $args[1];
        $targetName = $data->getUsername();

        switch($action){
            case 'set':
                $value = $args[2] ?? '';
                $data->setMeta($key, $value);
                $sender->sendMessage(LocaleManager::get('offline-meta-set', ['player' => $targetName, 'key' => $key, 'value' => $value]));
                return true;
            case 'unset':
                $data->unsetMeta($key);
                $sender->sendMessage(LocaleManager::get('offline-meta-unset', ['player' => $targetName, 'key' => $key]));
                return true;
            case 'get':
                $value = $data->getMeta($key);
                $sender->sendMessage(LocaleManager::get('meta-get-format', [
                    'key' => $key,
                    'value' => $value !== null ? $value : LocaleManager::get('meta-get-not-set')
                ]));
                return true;
            default:
                $this->usage($sender, 'usage-user-meta-invalid');
                return false;
        }
    }

    private function prefixOffline(CommandSender $sender, \appgallery\uperms\player\OfflinePlayerData $data, array $args): bool{
        $action = strtolower($args[0] ?? '');
        $targetName = $data->getUsername();

        switch ($action) {
            case 'set':
                $prefix = implode(' ', array_slice($args, 1));
                if ($prefix === '') {
                    $this->usage($sender, 'usage-user-prefix-set');
                    return false;
                }
                $data->setPrefix($prefix);
                $sender->sendMessage(LocaleManager::get('offline-prefix-set', [
                    'player' => $targetName,
                    'prefix' => $prefix
                ]));
                return true;

            case 'unset':
                $data->setPrefix('');
                $sender->sendMessage(LocaleManager::get('offline-prefix-unset', [
                    'player' => $targetName
                ]));
                return true;

            case 'get':
                $current = $data->getPrefix();
                $prefixVal = $current !== '' ? $current : LocaleManager::get('user-prefix-none');
                $sender->sendMessage(LocaleManager::get('user-prefix-get', [
                    'player' => $targetName,
                    'prefix' => $prefixVal
                ]));
                return true;

            default:
                $this->usage($sender, 'usage-user-prefix');
                return false;
        }
    }

    private function suffixOffline(CommandSender $sender, \appgallery\uperms\player\OfflinePlayerData $data, array $args): bool{
        $action = strtolower($args[0] ?? '');
        $targetName = $data->getUsername();

        switch ($action) {
            case 'set':
                $suffix = implode(' ', array_slice($args, 1));
                if ($suffix === '') {
                    $this->usage($sender, 'usage-user-suffix-set');
                    return false;
                }
                $data->setSuffix($suffix);
                $sender->sendMessage(LocaleManager::get('offline-suffix-set', [
                    'player' => $targetName,
                    'suffix' => $suffix
                ]));
                return true;

            case 'unset':
                $data->setSuffix('');
                $sender->sendMessage(LocaleManager::get('offline-suffix-unset', [
                    'player' => $targetName
                ]));
                return true;

            case 'get':
                $current = $data->getSuffix();
                $suffixVal = $current !== '' ? $current : LocaleManager::get('user-suffix-none');
                $sender->sendMessage(LocaleManager::get('user-suffix-get', [
                    'player' => $targetName,
                    'suffix' => $suffixVal
                ]));
                return true;

            default:
                $this->usage($sender, 'usage-user-suffix');
                return false;
        }
    }

    private function infoOffline(CommandSender $sender, \appgallery\uperms\player\OfflinePlayerData $data): bool{
        $sender->sendMessage(LocaleManager::get('offline-info-header', ['player' => $data->getUsername()]));

        $sender->sendMessage(LocaleManager::get('user-info-groups'));
        foreach($data->getGroups() as $groupId => $expiresAt){
            $group = $this->groupManager->get($groupId);
            $name = $group?->getDisplayName() ?? $groupId;
            $expiry = $expiresAt !== null
                ? LocaleManager::get('user-info-group-expiry', ['duration' => DurationParser::format($expiresAt - time())])
                : LocaleManager::get('user-info-group-permanent');
            $sender->sendMessage(LocaleManager::get('user-info-group-format', [
                'group' => $name,
                'expiry' => $expiry
            ]));
        }

        $nodes = $data->getPersonalNodes();
        if(!empty($nodes)){
            $sender->sendMessage(LocaleManager::get('user-info-overrides'));
            foreach($nodes as $node){
                $icon = $node->getState() ? TextFormat::GREEN . "+" : TextFormat::RED . "-";
                $expiry = $node->isPermanent()
                    ? LocaleManager::get('user-info-override-permanent')
                    : LocaleManager::get('user-info-override-expiry', ['duration' => DurationParser::format($node->getExpiresAt() - time())]);
                $sender->sendMessage(LocaleManager::get('user-info-override-format', [
                    'state' => $icon,
                    'permission' => $node->getPermission(),
                    'expiry' => $expiry
                ]));
            }
        }

        $prefixVal = $data->getPrefix() ?: LocaleManager::get('user-info-none');
        $suffixVal = $data->getSuffix() ?: LocaleManager::get('user-info-none');
        $sender->sendMessage(LocaleManager::get('user-info-prefix-suffix', [
            'prefix' => $prefixVal,
            'suffix' => $suffixVal
        ]));

        return true;
    }

    private function auditOffline(CommandSender $sender, \appgallery\uperms\player\OfflinePlayerData $data): bool{
        $sender->sendMessage(LocaleManager::get('offline-audit-unavailable'));
        return false;
    }
}