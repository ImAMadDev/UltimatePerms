<?php

declare(strict_types=1);

namespace appgallery\uperms\storage\async;

use appgallery\uperms\storage\IStorage;
use Closure;

/**
 * Operación asincrona para guardar datos de un jugador.
 */
class SavePlayerAsync extends AsyncOperation{
    private readonly string $serializedData;

    public function __construct(
        array $config,
        string $dataPath,
        array $data,
        ?Closure $onSuccess,
        ?Closure $onFailure,
    ){
        parent::__construct($config, $dataPath, $onSuccess, $onFailure);
        $this->serializedData = json_encode($data);
    }

    protected function executeOperation(IStorage $storage): bool{
        $data = json_decode($this->serializedData, true);
        $storage->savePlayer($data);
        return true;
    }

    protected function onSuccess(mixed $result, ?Closure $onSuccess): void{
        if($onSuccess !== null){
            $onSuccess();
        } else{
            $data = json_decode($this->serializedData, true);
            $xuid = $data['xuid'] ?? 'unknown';
            \GlobalLogger::get()->debug("[UltimatePerms] Jugador guardado: {$xuid}");
        }
    }
}