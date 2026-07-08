<?php

declare(strict_types=1);

namespace appgallery\uperms\storage\async;

use appgallery\uperms\storage\IStorage;
use appgallery\uperms\storage\StorageFactory;
use Closure;
use pocketmine\scheduler\AsyncTask;

/**
 * Clase base para todas las operaciones asincronas de storage.
 * Proporciona utilidades comunes, conexión local en el Worker Thread y manejo de errores.
 */
abstract class AsyncOperation extends AsyncTask{

    /** @var string|null */
    protected ?string $error = null;

    private readonly string $config;
    private readonly string $dataPath;

    public function __construct(
        array                   $config,
        string                  $dataPath,
        ?Closure                $onSuccess = null,
        ?Closure                $onFailure = null,
    ){
        $this->config = json_encode($config);
        $this->dataPath = $dataPath;
        if($onSuccess !== null){
            $this->storeLocal('onSuccess', $onSuccess);
        }
        if($onFailure !== null){
            $this->storeLocal('onFailure', $onFailure);
        }
    }

    /**
     * Ejecuta la operación en el thread asincronico usando un storage local.
     */
    abstract protected function executeOperation(IStorage $storage): mixed;

    /**
     * Se ejecuta en el thread principal cuando la operación finaliza exitosamente.
     *
     * @param mixed $result Resultado de executeOperation()
     * @param Closure|null $onSuccess Callback de éxito recuperado del TLS
     */
    abstract protected function onSuccess(mixed $result, ?Closure $onSuccess): void;

    /**
     * Se ejecuta en el thread principal si ocurre un error y no hay callback definido.
     *
     * @param string $error Mensaje de error
     */
    protected function onFailure(string $error): void{
        \GlobalLogger::get()->error(
            "[UltimatePerms] Async operation failed: {$error}",
        );
    }

    /**
     * @inheritDoc
     */
    public function onRun(): void{
        try{
            $configArray = json_decode($this->config, true);
            $storage = StorageFactory::create($configArray, $this->dataPath);
            $storage->init();

            $this->setResult($this->executeOperation($storage));

            $storage->close();
        } catch(\Throwable $e){
            $this->error = $e->getMessage();
            \GlobalLogger::get()->debug(
                "[UltimatePerms] AsyncTask error: " . $e->getTraceAsString(),
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function onCompletion(): void{
        if($this->error !== null){
            /** @var Closure|null $onFailure */
            $onFailure = $this->fetchLocal('onFailure');
            if($onFailure !== null){
                $onFailure($this->error);
            } else{
                $this->onFailure($this->error);
            }
            return;
        }

        /** @var Closure|null $onSuccess */
        $onSuccess = $this->fetchLocal('onSuccess');
        $this->onSuccess($this->getResult(), $onSuccess);
    }

    /**
     * Obtiene el resultado de la operación.
     *
     * @return mixed
     */
    public function getResult(): mixed{
        return parent::getResult();
    }

    /**
     * Verifica si la operación resultó en error.
     *
     * @return bool
     */
    public function hasError(): bool{
        return $this->error !== null;
    }

    /**
     * Obtiene el mensaje de error si la operación falló.
     *
     * @return string|null
     */
    public function getError(): ?string{
        return $this->error;
    }
}
