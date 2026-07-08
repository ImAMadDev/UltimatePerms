<?php

declare(strict_types=1);

namespace appgallery\uperms\permission\node;

final class Node{

    public function __construct(
        private readonly string $permission,
        private readonly bool   $state,
        private readonly ?int   $expiresAt = null,  // null = permanente, int = Unix timestamp
        private readonly array  $context = [],    // ["world" => "spawn", "gamemode" => "survival"]
    ){
    }

    // ── Getters ──────────────────────────────────────────────────────

    public function getPermission(): string{
        return $this->permission;
    }

    public function getState(): bool{
        return $this->state;
    }

    public function getExpiresAt(): ?int{
        return $this->expiresAt;
    }

    /**
     * @return array<string, string>
     */
    public function getContext(): array{
        return $this->context;
    }

    // ── Estado del nodo ──────────────────────────────────────────────

    public function isPermanent(): bool{
        return $this->expiresAt === null;
    }

    public function isExpired(): bool{
        return $this->expiresAt !== null && time() > $this->expiresAt;
    }

    public function isActive(): bool{
        return !$this->isExpired();
    }

    public function isGlobal(): bool{
        return empty($this->context);
    }

    /**
     * Verifica si este nodo aplica dado el contexto actual del jugador.
     * Un nodo global (sin contexto) siempre aplica.
     * Un nodo con contexto aplica solo si el jugador satisface TODOS los pares.
     *
     * @param array<string, string> $playerContext
     */
    public function appliesIn(array $playerContext): bool{
        if($this->isGlobal()){
            return true;
        }

        foreach($this->context as $key => $value){
            if(($playerContext[$key] ?? null) !== $value){
                return false;
            }
        }

        return true;
    }

    /**
     * Verifica si este nodo cubre un permiso concreto.
     * Soporta wildcards: "pocketmine.command.*" cubre "pocketmine.command.kick".
     */
    public function covers(string $permission): bool{
        if($this->permission === $permission){
            return true;
        }

        if(!str_ends_with($this->permission, '.*')){
            return false;
        }

        // "pocketmine.command.*" → prefix = "pocketmine.command."
        $prefix = substr($this->permission, 0, -1);
        return str_starts_with($permission, $prefix);
    }

    public function isWildcard(): bool{
        return str_ends_with($this->permission, '.*') || $this->permission === '*';
    }

    // ── Serialización ────────────────────────────────────────────────

    /**
     * Para guardar en YAML / JSON.
     * @return array<string, mixed>
     */
    public function serialize(): array{
        return [
            'permission' => $this->permission,
            'state' => $this->state,
            'expiresAt' => $this->expiresAt,
            'context' => $this->context,
        ];
    }

    /**
     * Para reconstruir desde YAML / JSON.
     * @param array<string, mixed> $data
     */
    public static function deserialize(array $data): self{
        return new self(
            permission: (string)$data['permission'],
            state: (bool)$data['state'],
            expiresAt: isset($data['expiresAt']) ? (int)$data['expiresAt'] : null,
            context: (array)($data['context'] ?? []),
        );
    }

    // ── Utilidades ───────────────────────────────────────────────────

    /**
     * Crea una copia del nodo con un estado diferente.
     */
    public function withState(bool $state): self{
        return new self($this->permission, $state, $this->expiresAt, $this->context);
    }

    /**
     * Crea una copia del nodo con una expiración diferente.
     */
    public function withExpiry(?int $expiresAt): self{
        return new self($this->permission, $this->state, $expiresAt, $this->context);
    }

    public function __toString(): string{
        $state = $this->state ? '+' : '-';
        $expiry = $this->expiresAt !== null ? " (expires: {$this->expiresAt})" : '';
        $ctx = !empty($this->context)
            ? ' [' . implode(', ', array_map(fn($k, $v) => "{$k}={$v}", array_keys($this->context), $this->context)) . ']'
            : '';

        return "{$state}{$this->permission}{$expiry}{$ctx}";
    }
}