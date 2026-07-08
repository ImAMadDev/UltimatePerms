<?php

declare(strict_types=1);

namespace appgallery\uperms\storage;

use appgallery\uperms\group\Group;
use appgallery\uperms\storage\async\BulkSavePlayerAsync;
use appgallery\uperms\storage\async\DeleteGroupAsync;
use appgallery\uperms\storage\async\DeletePlayerAsync;
use appgallery\uperms\storage\async\LoadGroupsAsync;
use appgallery\uperms\storage\async\LoadPlayerAsync;
use appgallery\uperms\storage\async\SaveGroupAsync;
use appgallery\uperms\storage\async\SavePlayerAsync;
use Closure;
use pocketmine\Server;

/**
 * Wrapper asincronico para operaciones de storage.
 * Delega todas las operaciones pesadas a AsyncTasks.
 *
 * Este wrapper es thread-safe y ejecuta callbacks cuando las operaciones
 * asincronas completanen el thread principal de PocketMine.
 */
final class AsyncStorageManager implements IAsyncStorage{
    public function __construct(
        private readonly array  $config,
        private readonly string $dataPath,
    ){
    }

    /**
     * Carga grupos de forma asincrona.
     *
     * @param Closure(array<string, mixed>): void $onSuccess Callback con datos crudos
     * @param Closure(string): void|null $onFailure Callback opcional de error
     */
    public function loadGroupsAsync(Closure $onSuccess, ?Closure $onFailure = null): void{
        Server::getInstance()->getAsyncPool()->submitTask(new LoadGroupsAsync($this->config, $this->dataPath, $onSuccess, $onFailure));
    }

    /**
     * Guarda un grupo de forma asincrona.
     *
     * @param Group $group Grupo a guardar
     * @param Closure(): void|null $onSuccess Callback opcional de éxito
     * @param Closure(string): void|null $onFailure Callback opcional de error
     */
    public function saveGroupAsync(Group $group, ?Closure $onSuccess = null, ?Closure $onFailure = null): void{
        Server::getInstance()->getAsyncPool()->submitTask(new SaveGroupAsync($this->config, $this->dataPath, $group, $onSuccess, $onFailure));
    }

    /**
     * Elimina un grupo de forma asincrona.
     *
     * @param string $groupId ID del grupo a eliminar
     * @param Closure(): void|null $onSuccess Callback opcional de éxito
     * @param Closure(string): void|null $onFailure Callback opcional de error
     */
    public function deleteGroupAsync(string $groupId, ?Closure $onSuccess = null, ?Closure $onFailure = null): void{
        Server::getInstance()->getAsyncPool()->submitTask(new DeleteGroupAsync($this->config, $this->dataPath, $groupId, $onSuccess, $onFailure));
    }

    /**
     * Carga datos de un jugador de forma asincrona.
     *
     * @param string $xuid XUID del jugador
     * @param Closure(array<string, mixed>|null): void $onSuccess Callback con datos o null si no existe
     * @param Closure(string): void|null $onFailure Callback opcional de error
     */
    public function loadPlayerAsync(string $xuid, Closure $onSuccess, ?Closure $onFailure = null): void{
        Server::getInstance()->getAsyncPool()->submitTask(new LoadPlayerAsync($this->config, $this->dataPath, $xuid, $onSuccess, $onFailure));
    }

    /**
     * Guarda datos de un jugador de forma asincrona.
     *
     * @param array<string, mixed> $data Datos serializados del jugador
     * @param Closure(): void|null $onSuccess Callback opcional de éxito
     * @param Closure(string): void|null $onFailure Callback opcional de error
     */
    public function savePlayerAsync(array $data, ?Closure $onSuccess = null, ?Closure $onFailure = null): void{
        Server::getInstance()->getAsyncPool()->submitTask(new SavePlayerAsync($this->config, $this->dataPath, $data, $onSuccess, $onFailure));
    }

    public function saveBulkPlayerAsync(array $players, ?Closure $onSuccess = null, ?Closure $onFailure = null): void{
        Server::getInstance()->getAsyncPool()->submitTask(new BulkSavePlayerAsync($this->config, $this->dataPath, $players, $onSuccess, $onFailure));
    }

    /**
     * Elimina datos de un jugador de forma asincrona.
     *
     * @param string $xuid XUID del jugador
     * @param Closure(): void|null $onSuccess Callback opcional de éxito
     * @param Closure(string): void|null $onFailure Callback opcional de error
     */
    public function deletePlayerAsync(string $xuid, ?Closure $onSuccess = null, ?Closure $onFailure = null): void{
        Server::getInstance()->getAsyncPool()->submitTask(new DeletePlayerAsync($this->config, $this->dataPath, $xuid, $onSuccess, $onFailure));
    }

    /**
     * Cierra el storage backend (noop ya que las conexiones se abren/cierran por task).
     */
    public function close(): void{
    }
}