<?php

declare(strict_types=1);

namespace appgallery\uperms\player;

use appgallery\uperms\permission\node\Node;

final class OfflinePlayerData{

    /** @var array<string, int|null> */
    private array $groups = [];

    /** @var Node[] */
    private array $nodes = [];

    private string $prefix = '';
    private string $suffix = '';

    /** @var array<string, string> */
    private array $meta = [];

    public function __construct(
        private readonly string $xuid,
        private readonly string $username
    ){
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromStorage(array $data): self{
        $self = new self(
            (string)$data['xuid'],
            (string)$data['username']
        );

        $self->groups = (array)($data['groups'] ?? []);

        $rawNodes = (array)($data['permissions'] ?? []);
        $self->nodes = array_map(
            fn(array $raw) => Node::deserialize($raw),
            $rawNodes
        );

        $self->prefix = (string)($data['prefix'] ?? '');
        $self->suffix = (string)($data['suffix'] ?? '');
        $self->meta = (array)($data['meta'] ?? []);

        return $self;
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(): array{
        return [
            'xuid' => $this->xuid,
            'username' => $this->username,
            'groups' => $this->groups,
            'permissions' => array_map(
                fn(Node $n) => $n->serialize(),
                $this->nodes
            ),
            'prefix' => $this->prefix !== '' ? $this->prefix : null,
            'suffix' => $this->suffix !== '' ? $this->suffix : null,
            'meta' => $this->meta,
        ];
    }

    // ── Getters ───────────────────────────────────────────────────────

    public function getXuid(): string{
        return $this->xuid;
    }

    public function getUsername(): string{
        return $this->username;
    }

    // ── Groups ────────────────────────────────────────────────────────

    public function addGroup(string $groupId, ?int $expiresAt = null): void{
        $this->groups[$groupId] = $expiresAt;
    }

    public function removeGroup(string $groupId): void{
        unset($this->groups[$groupId]);
    }

    public function setGroups(array $groups): void{
        $this->groups = $groups;
    }

    /**
     * @return array<string, int|null>
     */
    public function getGroups(): array{
        return $this->groups;
    }

    public function hasGroup(string $groupId): bool{
        return array_key_exists($groupId, $this->groups);
    }

    // ── Nodes ─────────────────────────────────────────────────────────

    public function addNode(Node $node): void{
        foreach($this->nodes as $i => $existing){
            if($existing->getPermission() === $node->getPermission()){
                $this->nodes[$i] = $node;
                return;
            }
        }
        $this->nodes[] = $node;
    }

    public function removeNode(string $permission): void{
        foreach($this->nodes as $i => $node){
            if($node->getPermission() === $permission){
                array_splice($this->nodes, $i, 1);
                return;
            }
        }
    }

    /**
     * @return Node[]
     */
    public function getPersonalNodes(): array{
        return $this->nodes;
    }

    // ── Prefix & Suffix ───────────────────────────────────────────────

    public function setPrefix(string $prefix): void{
        $this->prefix = $prefix;
    }

    public function getPrefix(): string{
        return $this->prefix;
    }

    public function setSuffix(string $suffix): void{
        $this->suffix = $suffix;
    }

    public function getSuffix(): string{
        return $this->suffix;
    }

    // ── Meta ──────────────────────────────────────────────────────────

    public function setMeta(string $key, string $value): void{
        $this->meta[$key] = $value;
    }

    public function unsetMeta(string $key): void{
        unset($this->meta[$key]);
    }

    public function getMeta(string $key): ?string{
        return $this->meta[$key] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function getAllMeta(): array{
        return $this->meta;
    }
}
