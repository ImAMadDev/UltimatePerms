<?php

declare(strict_types=1);

namespace appgallery\uperms\storage\async;

use appgallery\uperms\storage\AsyncStorageManager;
use appgallery\uperms\storage\JsonStorage;
use pocketmine\utils\TextFormat;

/**
 * Ejemplo práctico de uso del AsyncStorageManager.
 * Este archivo muestra los patrones recomendados para usar operaciones asincronas.
 */
final class AsyncStorageExample
{
    private AsyncStorageManager $asyncStorage;

    public function __construct(string $dataPath)
    {
        // Crear storage sincronico
        $storage = new JsonStorage($dataPath);
        $storage->init();

        // Envolver en manager asincronico
        $this->asyncStorage = new AsyncStorageManager($storage);
    }

    /**
     * Ejemplo 1: Cargar todos los grupos cuando el servidor inicia.
     */
    public function loadAllGroupsOnStartup(): void
    {
        \GlobalLogger::get()->info(TextFormat::AQUA . "[AsyncExample] Cargando grupos...");

        $this->asyncStorage->loadGroupsAsync(
            onSuccess: function(array $groupsData) {
                \GlobalLogger::get()->info(
                    TextFormat::GREEN . "[AsyncExample] Se cargaron " . count($groupsData) . " grupos exitosamente",
                );

                foreach ($groupsData as $groupData) {
                    $id = $groupData['id'] ?? 'unknown';
                    \GlobalLogger::get()->debug("[AsyncExample] - Grupo: $id");
                }
            },
            onFailure: function(string $error) {
                \GlobalLogger::get()->error(
                    TextFormat::RED . "[AsyncExample] Error al cargar grupos: $error",
                );
            },
        );
    }

    /**
     * Ejemplo 2: Guardar un grupo sin bloquear operaciones del servidor.
     */
    public function saveGroupNonBlocking(array $groupData): void
    {
        \GlobalLogger::get()->debug("[AsyncExample] Guardando grupo asincronamente...");

        $this->asyncStorage->savePlayerAsync(
            $groupData,
            onSuccess: function() {
                $groupId = $groupData['id'] ?? 'unknown';
                \GlobalLogger::get()->info(
                    TextFormat::GREEN . "[AsyncExample] Grupo guardado: $groupId",
                );
            },
            onFailure: function(string $error) {
                \GlobalLogger::get()->error(
                    TextFormat::RED . "[AsyncExample] Falló guardar grupo: $error",
                );
            },
        );
    }

    /**
     * Ejemplo 3: Cargar datos de jugador cuando se conecta.
     */
    public function loadPlayerDataOnJoin(string $xuid, callable $callback): void
    {
        $this->asyncStorage->loadPlayerAsync(
            $xuid,
            onSuccess: function(?array $playerData) use ($xuid, $callback) {
                if ($playerData === null) {
                    \GlobalLogger::get()->debug(
                        "[AsyncExample] Jugador nuevo (sin datos previos): $xuid",
                    );
                    $callback(null);
                } else {
                    \GlobalLogger::get()->debug(
                        "[AsyncExample] Datos de jugador cargados: $xuid",
                    );
                    $callback($playerData);
                }
            },
            onFailure: function(string $error) use ($xuid) {
                \GlobalLogger::get()->error(
                    "[AsyncExample] Error al cargar datos de $xuid: $error",
                );
            },
        );
    }

    /**
     * Ejemplo 4: Guardar datos de jugador periódicamente.
     */
    public function autoSavePlayerData(array $playerData): void
    {
        // Este método se puede llamar cada N ticks
        $xuid = $playerData['xuid'] ?? 'unknown';

        $this->asyncStorage->savePlayerAsync(
            $playerData,
            onSuccess: function() use ($xuid) {
                \GlobalLogger::get()->debug("[AsyncExample] Auto-guardado: $xuid");
            },
            // Sin onFailure - usa el logging por defecto
        );
    }

    /**
     * Ejemplo 5: Operación en cadena (cargar -> procesar -> guardar).
     */
    public function loadProcessAndSave(string $xuid): void
    {
        $this->asyncStorage->loadPlayerAsync(
            $xuid,
            onSuccess: function(?array $playerData) use ($xuid) {
                if ($playerData === null) {
                    \GlobalLogger::get()->warning(
                        "[AsyncExample] No se puede procesar jugador inexistente: $xuid",
                    );
                    return;
                }

                // Procesar datos (en thread principal)
                $playerData['last_processed'] = time();
                $playerData['processed_count'] = ($playerData['processed_count'] ?? 0) + 1;

                // Guardar datos procesados (asincronamente)
                $this->asyncStorage->savePlayerAsync(
                    $playerData,
                    onSuccess: function() use ($xuid) {
                        \GlobalLogger::get()->info(
                            "[AsyncExample] Jugador procesado y guardado: $xuid",
                        );
                    },
                );
            },
        );
    }

    /**
     * Ejemplo 6: Limpiar jugador al desconectarse.
     */
    public function deletePlayerOnQuit(string $xuid): void
    {
        \GlobalLogger::get()->debug("[AsyncExample] Eliminando datos de jugador: $xuid");

        $this->asyncStorage->deletePlayerAsync(
            $xuid,
            onSuccess: function() use ($xuid) {
                \GlobalLogger::get()->debug("[AsyncExample] Jugador eliminado: $xuid");
            },
        );
    }

    /**
     * Cerrar resources (llamar en onDisable).
     */
    public function shutdown(): void
    {
        \GlobalLogger::get()->info("[AsyncExample] Cerrando storage...");
        $this->asyncStorage->close();
    }
}

/**
 * Ejemplo de integración en un plugin.
 */
final class PluginStorageIntegration
{
    private AsyncStorageExample $example;

    public function onPluginEnable(string $dataPath): void
    {
        $this->example = new AsyncStorageExample($dataPath);

        // Cargar grupos al iniciar (asincronamente)
        $this->example->loadAllGroupsOnStartup();
    }

    public function onPlayerJoin(string $xuid): void
    {
        // Cargar datos del jugador asincronamente
        $this->example->loadPlayerDataOnJoin($xuid, function(?array $data) use ($xuid) {
            if ($data !== null) {
                // Aplicar permisos, roles, etc.
                echo "Datos del jugador $xuid cargados\n";
            }
        });
    }

    public function onPlayerSave(array $playerData): void
    {
        // Guardar datos sin bloquear
        $this->example->autoSavePlayerData($playerData);
    }

    public function onPlayerQuit(string $xuid): void
    {
        // Limpiar datos (opcional)
        // $this->example->deletePlayerOnQuit($xuid);
    }

    public function onPluginDisable(): void
    {
        $this->example->shutdown();
    }
}
