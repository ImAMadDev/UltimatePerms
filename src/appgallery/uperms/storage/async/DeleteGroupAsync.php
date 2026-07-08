<?php

declare(strict_types=1);

namespace appgallery\uperms\storage\async;

use appgallery\uperms\storage\IStorage;
use Closure;

/**
 * Operación asincrona para eliminar un grupo.
 */
class DeleteGroupAsync extends AsyncOperation{
    public function __construct(
        array $config,
        string $dataPath,
        private readonly string $groupId,
        ?Closure $onSuccess,
        ?Closure $onFailure,
    ){
        parent::__construct($config, $dataPath, $onSuccess, $onFailure);
    }

    protected function executeOperation(IStorage $storage): bool{
        $storage->deleteGroup($this->groupId);
        return true;
    }

    protected function onSuccess(mixed $result, ?Closure $onSuccess): void{
        if($onSuccess !== null){
            $onSuccess();
        } else{
            \GlobalLogger::get()->debug(
                "[UltimatePerms] Grupo eliminado: {$this->groupId}",
            );
        }
    }
}