<?php

declare(strict_types=1);

namespace appgallery\uperms\storage;

use appgallery\uperms\group\Group;
use Closure;

interface IAsyncStorage{

    /**
     * @param Closure(array<string, mixed>|null): void $onSuccess
     * @param Closure(string): void|null $onFailure
     */
    public function loadPlayerAsync(string $xuid, Closure $onSuccess, ?Closure $onFailure = null): void;

    /**
     * @param array<string, mixed> $data
     * @param Closure(): void|null $onSuccess
     * @param Closure(string): void|null $onFailure
     */
    public function savePlayerAsync(array $data, ?Closure $onSuccess = null, ?Closure $onFailure = null): void;

    /**
     * @param array<string, array<string, mixed>> $players
     * @param Closure(): void|null $onSuccess
     * @param Closure(string): void|null $onFailure
     */
    public function saveBulkPlayerAsync(array $players, ?Closure $onSuccess = null, ?Closure $onFailure = null): void;

    /**
     * @param Closure(): void|null $onSuccess
     * @param Closure(string): void|null $onFailure
     */
    public function deletePlayerAsync(string $xuid, ?Closure $onSuccess = null, ?Closure $onFailure = null): void;

    /**
     * @param Closure(array<string, mixed>): void $onSuccess
     * @param Closure(string): void|null $onFailure
     */
    public function loadGroupsAsync(Closure $onSuccess, ?Closure $onFailure = null): void;

    /**
     * @param Closure(): void|null $onSuccess
     * @param Closure(string): void|null $onFailure
     */
    public function saveGroupAsync(Group $group, ?Closure $onSuccess = null, ?Closure $onFailure = null): void;

    /**
     * @param Closure(): void|null $onSuccess
     * @param Closure(string): void|null $onFailure
     */
    public function deleteGroupAsync(string $groupId, ?Closure $onSuccess = null, ?Closure $onFailure = null): void;

    public function close(): void;
}
