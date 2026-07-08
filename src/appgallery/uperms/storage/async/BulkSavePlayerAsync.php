<?php

declare(strict_types=1);

namespace appgallery\uperms\storage\async;

use appgallery\uperms\storage\IStorage;
use Closure;

class BulkSavePlayerAsync extends AsyncOperation{
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
        foreach($data as $playerData){
            $storage->savePlayer($playerData);
        }
        return true;
    }

    protected function onSuccess(mixed $result, ?Closure $onSuccess): void{
        if($onSuccess !== null){
            $onSuccess();
        } else{
            \GlobalLogger::get()->debug("[UltimatePerms] Bulk save completed.");
        }
    }
}