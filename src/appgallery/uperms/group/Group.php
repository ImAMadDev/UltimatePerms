<?php

declare(strict_types=1);

namespace appgallery\uperms\group;

use appgallery\uperms\permission\node\Node;

final class Group{

    /**
     * @param string $id ID interno inmutable  ("youtuber")
     * @param string $displayName Nombre visual         ("§cYouTuber")
     * @param int $weight Prioridad de resolución (mayor = más prioritario)
     * @param ?string $parent ID del grupo padre
     * @param Node[] $nodes Permisos asignados a este grupo
     * @param string $prefix
     * @param string $suffix
     * @param array<string, string> $meta
     */
    public function __construct(
        private readonly string $id,
        private string          $displayName,
        private int             $weight = 0,
        private ?string          $parent = null,
        private array           $nodes = [],
        private string          $prefix = '',
        private string          $suffix = '',
        private array           $meta = [],
    ){
    }

    // ── Identidad ─────────────────────────────────────────────────────

    public function getId(): string{
        return $this->id;
    }

    public function getDisplayName(): string{
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): void{
        $this->displayName = $displayName;
    }

    public function getWeight(): int{
        return $this->weight;
    }

    public function setWeight(int $weight): void{
        $this->weight = $weight;
    }

    // ── Herencia ──────────────────────────────────────────────────────


    public function getParent(): ?string{
        return $this->parent;
    }

    public function setParent(?string $groupId): void{
        $this->parent = $groupId;
    }


    public function isParent(string $groupId): bool{
        return $this->parent === $groupId;
    }

    // ── Nodos ─────────────────────────────────────────────────────────

    /**
     * @return Node[]
     */
    public function getNodes(): array{
        return $this->nodes;
    }

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
        $this->nodes = array_values(
            array_filter($this->nodes, fn(Node $n) => $n->getPermission() !== $permission)
        );
    }

    public function getNode(string $permission): ?Node{
        foreach($this->nodes as $node){
            if($node->getPermission() === $permission){
                return $node;
            }
        }
        return null;
    }

    /**
     * Retorna solo los nodos activos (no expirados) que aplican en el contexto dado.
     *
     * @param array<string, string> $context
     * @return Node[]
     */
    public function getActiveNodes(array $context = []): array{
        return array_values(
            array_filter(
                $this->nodes,
                fn(Node $n) => $n->isActive() && $n->appliesIn($context)
            )
        );
    }

    /**
     * Limpia nodos expirados y retorna los que se eliminaron.
     * @return Node[]
     */
    public function flushExpiredNodes(): array{
        $expired = [];
        $remaining = [];

        foreach($this->nodes as $node){
            if($node->isExpired()){
                $expired[] = $node;
            } else{
                $remaining[] = $node;
            }
        }

        $this->nodes = $remaining;
        return $expired;
    }

    // ── Prefix / Suffix ───────────────────────────────────────────────

    public function getPrefix(): string{
        return $this->prefix;
    }

    public function setPrefix(string $prefix): void{
        $this->prefix = $prefix;
    }

    public function getSuffix(): string{
        return $this->suffix;
    }

    public function setSuffix(string $suffix): void{
        $this->suffix = $suffix;
    }

    // ── Meta ──────────────────────────────────────────────────────────

    /**
     * @return array<string, string>
     */
    public function getMeta(): array{
        return $this->meta;
    }

    public function getMetaValue(string $key): ?string{
        return $this->meta[$key] ?? null;
    }

    public function setMetaValue(string $key, string $value): void{
        $this->meta[$key] = $value;
    }

    public function unsetMetaValue(string $key): void{
        unset($this->meta[$key]);
    }

    // ── Serialización ─────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    public function serialize(): array{
        return [
            'id' => $this->id,
            'displayName' => $this->displayName,
            'weight' => $this->weight,
            'parent' => $this->parent,
            'permissions' => array_map(fn(Node $n) => $n->serialize(), $this->nodes),
            'prefix' => $this->prefix !== '' ? $this->prefix : null,
            'suffix' => $this->suffix !== '' ? $this->suffix : null,
            'meta' => $this->meta,
        ];
    }

    public static function deserialize(array $data): self{
        return new self(
            id: (string)$data['id'],
            displayName: (string)($data['displayName'] ?? $data['id']),
            weight: (int)($data['weight'] ?? 0),
            parent: (string)($data['parent'] ?? null),
            nodes: array_map(
                fn(array $raw) => Node::deserialize($raw),
                (array)($data['permissions'] ?? [])
            ),
            prefix: (string)($data['prefix'] ?? ''),
            suffix: (string)($data['suffix'] ?? ''),
            meta: (array)($data['meta'] ?? []),
        );
    }

    public function __toString(): string{
        return "[Group:{$this->id} weight={$this->weight} parent=" . $this->parent ?? "none" . "]";
    }
}