<?php

declare(strict_types=1);

namespace appgallery\uperms\player\context;

use pocketmine\player\Player;
use pocketmine\utils\SingletonTrait;

final class ContextManager {
    use SingletonTrait;

    /** @var IContextCalculator[] */
    private array $calculators = [];

    public function __construct() {
        // Registrar calculadores built-in
        $this->register(new WorldContextCalculator());
        $this->register(new GamemodeContextCalculator());
    }

    /**
     * Registra un calculador externo (otros plugins).
     */
    public function register(IContextCalculator $calculator): void {
        $this->calculators[$calculator->getKey()] = $calculator;
    }

    public function unregister(string $key): void {
        unset($this->calculators[$key]);
    }

    /**
     * Calcula el contexto completo del jugador en este momento.
     * Llama a todos los calculadores registrados.
     *
     * @return array<string, string>
     */
    public function calculate(Player $player): array {
        $context = [];

        foreach ($this->calculators as $key => $calculator) {
            $value = $calculator->getValueFor($player);
            if ($value !== null) {
                $context[$key] = $value;
            }
        }

        return $context;
    }

    /**
     * Recalcula solo una clave del contexto.
     * Retorna el nuevo valor o null si el calculador no aplica.
     */
    public function recalculateKey(Player $player, string $key): ?string {
        return ($this->calculators[$key] ?? null)?->getValueFor($player);
    }

    /**
     * @return IContextCalculator[]
     */
    public function getCalculators(): array {
        return array_values($this->calculators);
    }
}