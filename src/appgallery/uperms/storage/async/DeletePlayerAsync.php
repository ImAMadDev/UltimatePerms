<?php

declare(strict_types=1);

namespace appgallery\uperms\storage\async;

use appgallery\uperms\storage\IStorage;
use Closure;

/**
 * Operación asincrona para eliminar datos de un jugador.
 */
class DeletePlayerAsync extends AsyncOperation{
    public function __construct(
        array $config,
        string $dataPath,
        private readonly string $xuid,
        ?Closure $onSuccess,
        ?Closure $onFailure,
    ){
        parent::__construct($config, $dataPath, $onSuccess, $onFailure);
    }

    protected function executeOperation(IStorage $storage): bool{
        $storage->deletePlayer($this->xuid);
        return true;
    }

    protected function onSuccess(mixed $result, ?Closure $onSuccess): void{
        if($onSuccess !== null){
            $onSuccess();
        } else{
            \GlobalLogger::get()->debug(
                "[UltimatePerms] Jugador eliminado: {$this->xuid}",
            );
        }
    }
}