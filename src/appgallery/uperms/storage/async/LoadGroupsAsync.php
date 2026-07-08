<?php

declare(strict_types=1);

namespace appgallery\uperms\storage\async;

use appgallery\uperms\group\Group;
use appgallery\uperms\group\GroupSerializer;
use appgallery\uperms\storage\IStorage;
use Closure;

/**
 * Operación asincrona para cargar grupos del storage.
 */
class LoadGroupsAsync extends AsyncOperation{
    public function __construct(
        array $config,
        string $dataPath,
        Closure $onSuccess,
        ?Closure $onFailure,
    ){
        parent::__construct($config, $dataPath, $onSuccess, $onFailure);
    }

    protected function executeOperation(IStorage $storage): array{
        $groups = $storage->loadGroups();
        $serialized = [];
        foreach($groups as $group){
            if($group instanceof Group){
                $serialized[] = GroupSerializer::toYaml($group);
            }
        }
        return $serialized;
    }

    protected function onSuccess(mixed $result, ?Closure $onSuccess): void{
        if($onSuccess !== null){
            $onSuccess($result);
        }
    }
}
