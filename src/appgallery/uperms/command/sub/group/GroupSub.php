<?php

declare(strict_types=1);

namespace appgallery\uperms\command\sub\group;

use appgallery\uperms\command\SubCommand;
use appgallery\uperms\group\GroupManager;
use appgallery\uperms\permission\node\Node;
use appgallery\uperms\permission\node\NodeRegistry;
use appgallery\uperms\player\SessionManager;
use appgallery\uperms\util\DurationParser;
use appgallery\uperms\locale\LocaleManager;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

final class GroupSub extends SubCommand{

    public function __construct(
        private readonly GroupManager   $groupManager,
        private readonly SessionManager $sessionManager,
    ){
    }

    public function getName(): string{
        return 'group';
    }

    public function getAliases(): array{
        return ['g'];
    }

    public function getDescription(): string{
        return 'Manage groups (create, perms, parents, meta)';
    }

    public function getPermission(): string{
        return 'ultimateperms.command.group';
    }

    /*
     * Routing:
     *   /uperms group list
     *   /uperms group <name> create [displayName] [weight]
     *   /uperms group <name> info
     *   /uperms group <name> delete [confirm]
     *   /uperms group <name> clone <newId> [displayName]
     *   /uperms group <name> perm <set|unset|list> <node> [true|false] [ctx=v] [time, ex: 1Y2M3w4d5h6m7s]
     *   /uperms group <name> parent <add|remove> <parentGroup>
     *   /uperms group <name> setweight <int>
     *   /uperms group <name> setprefix <text>
     *   /uperms group <name> setsuffix <text>
     *   /uperms group <name> meta <set|unset|get> <key> [value]
     */
    public function execute(CommandSender $sender, array $args): void{
        if(empty($args)){
            $this->sendHelp($sender);
            return;
        }

        // Caso especial: /uperms group list
        if(strtolower($args[0]) === 'list'){
            $this->listGroups($sender);
            return;
        }

        if(count($args) < 2){
            $this->sendHelp($sender);
            return;
        }

        $id = strtolower($args[0]);
        $action = strtolower($args[1]);

        match ($action) {
            'create' => $this->create($sender, $id, array_slice($args, 2)),
            'info' => $this->info($sender, $id),
            'delete' => $this->delete($sender, $id, array_slice($args, 2)),
            'clone' => $this->cloneGroup($sender, $id, array_slice($args, 2)),
            'perm' => $this->perm($sender, $id, array_slice($args, 2)),
            'parent' => $this->parent($sender, $id, array_slice($args, 2)),
            'setweight' => $this->setWeight($sender, $id, array_slice($args, 2)),
            'setprefix' => $this->setPrefix($sender, $id, array_slice($args, 2)),
            'setsuffix' => $this->setSuffix($sender, $id, array_slice($args, 2)),
            'meta' => $this->meta($sender, $id, array_slice($args, 2)),
            default => $this->sendHelp($sender),
        };
    }

    // ── list ──────────────────────────────────────────────────────────

    private function listGroups(CommandSender $sender): void{
        $groups = $this->groupManager->getRegistry()->getAllByWeight();
        $sender->sendMessage(LocaleManager::get('group-list-header', ['count' => (string)count($groups)]));

        foreach($groups as $group){
            $parent = $group->getParent() !== null
                ? LocaleManager::get('group-list-parent', ['parent' => $group->getParent()])
                : '';
            $sender->sendMessage(LocaleManager::get('group-list-item', [
                'displayname' => $group->getDisplayName(),
                'weight' => (string)$group->getWeight(),
                'parent' => $parent
            ]));
        }
    }

    // ── create ────────────────────────────────────────────────────────

    private function create(CommandSender $sender, string $id, array $args): void{
        if($this->groupManager->get($id) !== null){
            $sender->sendMessage(LocaleManager::get('group-already-exists', ['group' => $id]));
            return;
        }

        $displayName = $args[0] ?? $id;
        $weight = (int)($args[1] ?? 0);

        $this->groupManager->create($id, $displayName, $weight);
        $sender->sendMessage(LocaleManager::get('group-created', ['group' => $id]));
    }

    // ── info ──────────────────────────────────────────────────────────

    private function info(CommandSender $sender, string $id): void{
        $group = $this->groupManager->get($id);
        if($group === null){
            $sender->sendMessage(LocaleManager::get('group-not-found', ['group' => $id]));
            return;
        }

        $sender->sendMessage(LocaleManager::get('group-info-header', ['group' => $group->getDisplayName()]));
        $sender->sendMessage(LocaleManager::get('group-info-id', ['id' => $group->getId()]));
        $sender->sendMessage(LocaleManager::get('group-info-weight', ['weight' => (string)$group->getWeight()]));
        $sender->sendMessage(LocaleManager::get('group-info-parent', [
            'parent' => $group->getParent() !== null ? $group->getParent() : LocaleManager::get('user-info-none')
        ]));
        $sender->sendMessage(LocaleManager::get('group-info-prefix', [
            'prefix' => $group->getPrefix() ?: LocaleManager::get('user-info-none')
        ]));
        $sender->sendMessage(LocaleManager::get('group-info-suffix', [
            'suffix' => $group->getSuffix() ?: LocaleManager::get('user-info-none')
        ]));
        $sender->sendMessage(LocaleManager::get('group-info-nodes', ['count' => (string)count($group->getNodes())]));

        if(!empty($group->getMeta())){
            $sender->sendMessage(LocaleManager::get('group-info-meta-header'));
            foreach($group->getMeta() as $key => $value){
                $sender->sendMessage(LocaleManager::get('group-info-meta-item', ['key' => $key, 'value' => $value]));
            }
        }
    }

    // ── delete ────────────────────────────────────────────────────────

    private function delete(CommandSender $sender, string $id, array $args): void{
        $confirm = strtolower($args[0] ?? '') === 'confirm';

        if(!$confirm){
            $sender->sendMessage(LocaleManager::get('group-delete-confirm', ['group' => $id]));
            return;
        }

        if(!$this->groupManager->delete($id)){
            $sender->sendMessage(LocaleManager::get('group-delete-error', ['group' => $id]));
            return;
        }

        $this->sessionManager->refreshAll();
        $sender->sendMessage(LocaleManager::get('group-deleted', ['group' => $id]));
    }

    // ── clone ─────────────────────────────────────────────────────────

    private function cloneGroup(CommandSender $sender, string $id, array $args): void{
        $newId = strtolower($args[0] ?? '');
        $newDisplayName = $args[1] ?? $newId;

        if($newId === ''){
            $this->usage($sender, 'usage-group-clone');
            return;
        }

        $cloned = $this->groupManager->clone($id, $newId, $newDisplayName);
        if($cloned === null){
            $sender->sendMessage(LocaleManager::get('group-clone-error', ['group' => $id]));
            return;
        }

        $sender->sendMessage(LocaleManager::get('group-cloned', ['group' => $id, 'target' => $newId]));
    }

    // ── perm ──────────────────────────────────────────────────────────

    private function perm(CommandSender $sender, string $id, array $args): void{
        /*
         * SET   (con duración y contexto, igual que user):
         *   /uperms group vip perm set essentials.fly
         *   /uperms group vip perm set essentials.fly false
         *   /uperms group vip perm set essentials.fly true 30d
         *   /uperms group vip perm set essentials.fly false world=pvp
         *   /uperms group vip perm set essentials.fly true 7d world=spawn
         *
         * UNSET:
         *   /uperms group vip perm unset essentials.fly
         *
         * LIST:
         *   /uperms group vip perm list [page]
         */
        $group = $this->groupManager->get($id);
        if($group === null){
            $sender->sendMessage(LocaleManager::get('group-not-found', ['group' => $id]));
            return;
        }

        $action = strtolower($args[0] ?? '');

        switch($action){

            case 'set':
                $parsed = $this->parsePermArgs(array_slice($args, 1));

                if($parsed === null){
                    $this->usage($sender, 'usage-group-perm-set');
                    return;
                }

                $group->addNode(new Node(
                    permission: $parsed['node'],
                    state: $parsed['state'],
                    expiresAt: $parsed['expiresAt'],
                    context: $parsed['context'],
                ));

                $this->groupManager->save($group);
                $this->sessionManager->refreshByGroup($id);

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

                $sender->sendMessage(LocaleManager::get('group-perm-set-custom', [
                    'node' => $parsed['node'],
                    'state' => $stateStr,
                    'group' => $id,
                    'expiry' => $expiryStr,
                    'context' => $ctxStr
                ]));
                break;

            case 'unset':
                $node = $args[1] ?? null;

                if($node === null){
                    $this->usage($sender, 'usage-group-perm-unset');
                    return;
                }

                $group->removeNode($node);
                $this->groupManager->save($group);
                $this->sessionManager->refreshByGroup($id);

                $sender->sendMessage(LocaleManager::get('group-perm-unset', [
                    'node' => $node,
                    'group' => $id
                ]));
                break;

            case 'list':
                $page = max(1, (int)($args[1] ?? 1));
                $nodes = $group->getNodes();
                $chunks = array_chunk($nodes, 10);
                $total = max(1, count($chunks));
                $page = min($page, $total);

                $sender->sendMessage(LocaleManager::get('group-perm-list-header', [
                    'group' => $id,
                    'page' => (string)$page,
                    'total' => (string)$total
                ]));

                if(empty($nodes)){
                    $sender->sendMessage(LocaleManager::get('group-perm-list-empty'));
                    break;
                }

                foreach($chunks[$page - 1] ?? [] as $n){
                    $stateVal = $n->getState() ? TextFormat::GREEN . "✓" : TextFormat::RED . "✗";
                    $expiry = !$n->isPermanent()
                        ? TextFormat::AQUA . " [" . DurationParser::format($n->getExpiresAt() - time()) . "]"
                        : '';
                    $ctx = !$n->isGlobal()
                        ? TextFormat::DARK_GRAY . " [" . implode(', ', array_map(
                            fn($k, $v) => "{$k}={$v}",
                            array_keys($n->getContext()),
                            $n->getContext()
                        )) . "]"
                        : '';

                    $sender->sendMessage(LocaleManager::get('group-perm-list-item', [
                        'state' => $stateVal,
                        'permission' => $n->getPermission(),
                        'expiry' => $expiry,
                        'context' => $ctx
                    ]));
                }

                if($page < $total){
                    $sender->sendMessage(LocaleManager::get('group-perm-list-next', [
                        'group' => $id,
                        'next' => (string)($page + 1)
                    ]));
                }
                break;

            default:
                $this->usage($sender, 'usage-group-perm');
        }
    }

    // ── parent ────────────────────────────────────────────────────────

    private function parent(CommandSender $sender, string $id, array $args): void {
        // args: <set|unset> [parentGroup]
        $group = $this->groupManager->get($id);
        if ($group === null) {
            $sender->sendMessage(LocaleManager::get('group-not-found', ['group' => $id]));
            return;
        }

        $action   = strtolower($args[0] ?? '');
        $parentId = strtolower($args[1] ?? '');

        switch ($action) {
            case 'set':
                if ($parentId === '') {
                    $this->usage($sender, 'usage-group-parent-set');
                    return;
                }
                if ($this->groupManager->get($parentId) === null) {
                    $sender->sendMessage(LocaleManager::get('group-parent-not-found', ['parent' => $parentId]));
                    return;
                }
                if ($parentId === $id) {
                    $sender->sendMessage(LocaleManager::get('group-parent-self'));
                    return;
                }

                $group->setParent($parentId);

                if ($this->groupManager->getRegistry()->hasCycle($id)) {
                    $group->setParent(null);
                    $sender->sendMessage(LocaleManager::get('group-parent-cycle', ['parent' => $parentId]));
                    return;
                }

                $this->groupManager->save($group);
                $this->sessionManager->refreshByGroup($id);
                $sender->sendMessage(LocaleManager::get('group-parent-added', ['group' => $id, 'parent' => $parentId]));
                break;

            case 'unset':
                $group->setParent(null);
                $this->groupManager->save($group);
                $this->sessionManager->refreshByGroup($id);
                $sender->sendMessage(LocaleManager::get('group-parent-removed', ['group' => $id, 'parent' => $group->getParent() ?? '']));
                break;

            default:
                $this->usage($sender, 'usage-group-parent');
        }
    }

    // ── setweight / setprefix / setsuffix ─────────────────────────────

    private function setWeight(CommandSender $sender, string $id, array $args): void{
        $group = $this->groupManager->get($id);
        if($group === null){
            $sender->sendMessage(LocaleManager::get('group-not-found', ['group' => $id]));
            return;
        }

        $weight = (int)($args[0] ?? 0);
        $group->setWeight($weight);
        $this->groupManager->save($group);
        $this->sessionManager->refreshByGroup($id);
        $sender->sendMessage(LocaleManager::get('group-weight-set', ['group' => $id, 'weight' => (string)$weight]));
    }

    private function setPrefix(CommandSender $sender, string $id, array $args): void{
        $group = $this->groupManager->get($id);
        if($group === null){
            $sender->sendMessage(LocaleManager::get('group-not-found', ['group' => $id]));
            return;
        }

        $prefix = implode(' ', $args);
        $group->setPrefix($prefix);
        $this->groupManager->save($group);
        $this->sessionManager->refreshByGroup($id);
        $sender->sendMessage(LocaleManager::get('group-prefix-set', ['group' => $id, 'prefix' => $prefix]));
    }

    private function setSuffix(CommandSender $sender, string $id, array $args): void{
        $group = $this->groupManager->get($id);
        if($group === null){
            $sender->sendMessage(LocaleManager::get('group-not-found', ['group' => $id]));
            return;
        }

        $suffix = implode(' ', $args);
        $group->setSuffix($suffix);
        $this->groupManager->save($group);
        $this->sessionManager->refreshByGroup($id);
        $sender->sendMessage(LocaleManager::get('group-suffix-set', ['group' => $id, 'suffix' => $suffix]));
    }

    // ── meta ──────────────────────────────────────────────────────────

    private function meta(CommandSender $sender, string $id, array $args): void{
        $group = $this->groupManager->get($id);
        if($group === null){
            $sender->sendMessage(LocaleManager::get('group-not-found', ['group' => $id]));
            return;
        }

        $action = strtolower($args[0] ?? '');
        $key = $args[1] ?? null;

        if($key === null){
            $this->usage($sender, 'usage-group-meta');
            return;
        }

        switch($action){
            case 'set':
                $value = $args[2] ?? '';
                $group->setMetaValue($key, $value);
                $this->groupManager->save($group);
                $this->sessionManager->refreshByGroup($id);
                $sender->sendMessage(LocaleManager::get('group-meta-set', [
                    'key' => $key,
                    'value' => $value,
                    'group' => $id
                ]));
                break;
            case 'unset':
                $group->unsetMetaValue($key);
                $this->groupManager->save($group);
                $sender->sendMessage(LocaleManager::get('group-meta-unset', [
                    'key' => $key,
                    'group' => $id
                ]));
                break;
            case 'get':
                $value = $group->getMetaValue($key);
                $sender->sendMessage(LocaleManager::get('group-meta-get-format', [
                    'key' => $key,
                    'value' => $value !== null ? $value : LocaleManager::get('group-meta-get-not-set')
                ]));
                break;
            default:
                $this->usage($sender, 'usage-group-meta-invalid');
        }
    }

    // ── help + tab ────────────────────────────────────────────────────

    private function sendHelp(CommandSender $sender): void{
        $sender->sendMessage(LocaleManager::get('group-help-header'));
        $sender->sendMessage(LocaleManager::get('group-help-list'));
        $sender->sendMessage(LocaleManager::get('group-help-create'));
        $sender->sendMessage(LocaleManager::get('group-help-info'));
        $sender->sendMessage(LocaleManager::get('group-help-delete'));
        $sender->sendMessage(LocaleManager::get('group-help-clone'));
        $sender->sendMessage(LocaleManager::get('group-help-perm'));
        $sender->sendMessage(LocaleManager::get('group-help-parent'));
        $sender->sendMessage(LocaleManager::get('group-help-setweight'));
        $sender->sendMessage(LocaleManager::get('group-help-setprefix'));
        $sender->sendMessage(LocaleManager::get('group-help-setsuffix'));
        $sender->sendMessage(LocaleManager::get('group-help-meta'));
    }

    public function onTabComplete(CommandSender $sender, array $args): array{
        $groupIds = $this->groupManager->getAllIds();
        $actions = ['list', 'create', 'info', 'delete', 'clone', 'perm', 'parent', 'setweight', 'setprefix', 'setsuffix', 'meta'];

        if(count($args) === 1){
            $merged = array_merge(['list'], $groupIds);
            return array_filter($merged, fn($v) => str_starts_with(strtolower($v), strtolower($args[0])));
        }

        if(count($args) === 2){
            return array_filter($actions, fn($a) => str_starts_with($a, strtolower($args[1])));
        }

        return match (strtolower($args[1])) {
            'perm' => match (count($args)) {
                3 => array_filter(['set', 'unset', 'list'], fn($s) => str_starts_with($s, $args[2])),
                4 => array_filter(NodeRegistry::getInstance()->getAllPermissionNames(), fn($n) => str_starts_with($n, $args[3])),
                5 => array_filter(['true', 'false'], fn($s) => str_starts_with($s, $args[4])),
                default => [],
            },
            'parent' => match (count($args)) {
                3 => array_filter(['add', 'remove'], fn($s) => str_starts_with($s, $args[2])),
                4 => array_filter($groupIds, fn($g) => str_starts_with($g, $args[3])),
                default => [],
            },
            'meta' => count($args) === 3
                ? array_filter(['set', 'unset', 'get'], fn($s) => str_starts_with($s, $args[2]))
                : [],
            'delete' => count($args) === 3 ? ['confirm'] : [],
            default => [],
        };
    }
}