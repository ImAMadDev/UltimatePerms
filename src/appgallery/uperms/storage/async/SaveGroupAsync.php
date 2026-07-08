<?php

declare(strict_types=1);

namespace appgallery\uperms\storage\async;

use appgallery\uperms\group\Group;
use appgallery\uperms\storage\IStorage;
use Closure;

/**
 * Operación asincrona para guardar un grupo.
 */
class SaveGroupAsync extends AsyncOperation{
    private readonly string $groupId;
    private readonly string $serializedGroup;

    public function __construct(
        array $config,
        string $dataPath,
        Group $group,
        ?Closure $onSuccess,
        ?Closure $onFailure,
    ){
        parent::__construct($config, $dataPath, $onSuccess, $onFailure);
        $this->groupId = $group->getId();
        $this->serializedGroup = json_encode(\appgallery\uperms\group\GroupSerializer::toYaml($group));
    }

    protected function executeOperation(IStorage $storage): bool{
        $groupData = json_decode($this->serializedGroup, true);
        $group = \appgallery\uperms\group\GroupSerializer::fromYaml($this->groupId, $groupData);
        $storage->saveGroup($group);
        return true;
    }

    protected function onSuccess(mixed $result, ?Closure $onSuccess): void{
        if($onSuccess !== null){
            $onSuccess();
        } else{
            \GlobalLogger::get()->debug(
                "[UltimatePerms] Grupo guardado: {$this->groupId}",
            );
        }
    }
}