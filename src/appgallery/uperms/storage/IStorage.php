<?php

declare(strict_types=1);

namespace appgallery\uperms\storage;

use appgallery\uperms\group\Group;
use appgallery\uperms\session\PlayerSession;

interface IStorage{

    /**
     * Inicializa el backend (crea tablas, directorios, conexiones).
     * Llamado una vez en onEnable() antes de cualquier operación.
     */
    public function init(): void;

    // ── Grupos ────────────────────────────────────────────────────────

    /**
     * @return Group[]
     */
    public function loadGroups(): array;

    public function saveGroup(Group $group): void;

    public function deleteGroup(string $id): void;

    // ── Jugadores ─────────────────────────────────────────────────────

    /**
     * Retorna null si el jugador no existe en storage (jugador nuevo).
     *
     * @return array<string, mixed>|null
     */
    public function loadPlayer(string $xuid): ?array;

    /**
     * Persiste los datos serializados de una PlayerSession.
     *
     * @param array<string, mixed> $data PlayerSession::serialize()
     */
    public function savePlayer(array $data): void;

    public function deletePlayer(string $xuid): void;

    /**
     * Busca los datos de un jugador en storage por su nombre de usuario de forma case-insensitive.
     * Retorna null si el jugador no existe.
     *
     * @return array<string, mixed>|null
     */
    public function loadPlayerByName(string $username): ?array;

    // ── Ciclo de vida ─────────────────────────────────────────────────

    /**
     * Cierra conexiones, flush de buffers pendientes.
     * Llamado en onDisable().
     */
    public function close(): void;
}