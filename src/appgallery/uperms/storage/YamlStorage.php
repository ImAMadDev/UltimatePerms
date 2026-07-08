<?php

declare(strict_types=1);

namespace appgallery\uperms\storage;

use appgallery\uperms\group\Group;
use appgallery\uperms\group\GroupSerializer;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

final class YamlStorage implements IStorage{

    private string $groupsFile;
    private string $playersDir;

    public function __construct(private readonly string $dataPath){
        $this->groupsFile = $dataPath . 'groups.yml';
        $this->playersDir = $dataPath . 'players' . DIRECTORY_SEPARATOR;
    }

    public function init(): void{
        if(!is_dir($this->playersDir)){
            mkdir($this->playersDir, 0755, true);
        }

        if(!file_exists($this->groupsFile)){
            file_put_contents($this->groupsFile, '');
        }

        \GlobalLogger::get()->info(
            TextFormat::GRAY . "[UltimatePerms] Storage: YAML @ {$this->dataPath}"
        );
    }

    // ── Grupos ────────────────────────────────────────────────────────

    public function loadGroups(): array{
        $config = new Config($this->groupsFile, Config::YAML);
        $groups = [];

        foreach($config->getAll() as $id => $raw){
            if(!is_array($raw)){
                continue;
            }

            try{
                $groups[] = GroupSerializer::fromYaml((string)$id, $raw);
            } catch(\Throwable $e){
                \GlobalLogger::get()->error(
                    "[UltimatePerms] Failed to load group '{$id}': {$e->getMessage()}"
                );
            }
        }

        return $groups;
    }

    public function saveGroup(Group $group): void{
        $config = new Config($this->groupsFile, Config::YAML);
        $config->set($group->getId(), GroupSerializer::toYaml($group));
        $config->save();
    }

    public function deleteGroup(string $id): void{
        $config = new Config($this->groupsFile, Config::YAML);
        $config->remove($id);
        $config->save();
    }

    // ── Jugadores ─────────────────────────────────────────────────────

    public function loadPlayer(string $xuid): ?array{
        $file = $this->playerFile($xuid);

        if(!file_exists($file)){
            return null;
        }

        $config = new Config($file, Config::YAML);
        $data = $config->getAll();

        return !empty($data) ? $data : null;
    }

    public function savePlayer(array $data): void{
        $xuid = (string)$data['xuid'];
        $config = new Config($this->playerFile($xuid), Config::YAML);
        $config->setAll($data);
        $config->save();
    }

    public function deletePlayer(string $xuid): void{
        $file = $this->playerFile($xuid);

        if(file_exists($file)){
            unlink($file);
        }
    }

    public function loadPlayerByName(string $username): ?array{
        $files = glob($this->playersDir . '*.yml');
        if($files === false){
            return null;
        }

        foreach($files as $file){
            $config = new Config($file, Config::YAML);
            $data = $config->getAll();
            if(isset($data['username']) && strcasecmp((string)$data['username'], $username) === 0){
                return $data;
            }
        }

        return null;
    }

    public function close(): void{
        // YAML no tiene conexión persistente — noop
    }

    // ── Interno ───────────────────────────────────────────────────────

    private function playerFile(string $xuid): string{
        return $this->playersDir . $xuid . '.yml';
    }
}