<?php

declare(strict_types=1);

namespace appgallery\uperms\storage;

use appgallery\uperms\group\Group;
use appgallery\uperms\group\GroupSerializer;
use pocketmine\utils\TextFormat;

final class JsonStorage implements IStorage{

    private string $groupsFile;
    private string $playersDir;

    public function __construct(private readonly string $dataPath){
        $this->groupsFile = $dataPath . 'groups.json';
        $this->playersDir = $dataPath . 'players' . DIRECTORY_SEPARATOR;
    }

    public function init(): void{
        if(!is_dir($this->playersDir)){
            mkdir($this->playersDir, 0755, true);
        }

        if(!file_exists($this->groupsFile)){
            file_put_contents($this->groupsFile, '{}');
        }

        \GlobalLogger::get()->info(
            TextFormat::GRAY . "[UltimatePerms] Storage: JSON @ {$this->dataPath}"
        );
    }

    // ── Grupos ────────────────────────────────────────────────────────

    public function loadGroups(): array{
        $raw = $this->readJson($this->groupsFile);
        $groups = [];

        foreach($raw as $id => $data){
            if(!is_array($data)){
                continue;
            }

            try{
                $groups[] = GroupSerializer::fromYaml((string)$id, $data);
            } catch(\Throwable $e){
                \GlobalLogger::get()->error(
                    "[UltimatePerms] Failed to load group '{$id}': {$e->getMessage()}"
                );
            }
        }

        return $groups;
    }

    public function saveGroup(Group $group): void{
        $all = $this->readJson($this->groupsFile);
        $all[$group->getId()] = GroupSerializer::toYaml($group);
        $this->writeJson($this->groupsFile, $all);
    }

    public function deleteGroup(string $id): void{
        $all = $this->readJson($this->groupsFile);
        unset($all[$id]);
        $this->writeJson($this->groupsFile, $all);
    }

    // ── Jugadores ─────────────────────────────────────────────────────

    public function loadPlayer(string $xuid): ?array{
        $file = $this->playerFile($xuid);

        if(!file_exists($file)){
            return null;
        }

        $data = $this->readJson($file);
        return !empty($data) ? $data : null;
    }

    public function savePlayer(array $data): void{
        $this->writeJson($this->playerFile((string)$data['xuid']), $data);
    }

    public function deletePlayer(string $xuid): void{
        $file = $this->playerFile($xuid);

        if(file_exists($file)){
            unlink($file);
        }
    }

    public function loadPlayerByName(string $username): ?array{
        $files = glob($this->playersDir . '*.json');
        if($files === false){
            return null;
        }

        foreach($files as $file){
            $data = $this->readJson($file);
            if(isset($data['username']) && strcasecmp((string)$data['username'], $username) === 0){
                return $data;
            }
        }

        return null;
    }

    public function close(): void{
        // JSON no tiene conexión persistente — noop
    }

    // ── Interno ───────────────────────────────────────────────────────

    private function playerFile(string $xuid): string{
        return $this->playersDir . $xuid . '.json';
    }

    private function readJson(string $file): array{
        $content = file_get_contents($file);

        if($content === false || $content === ''){
            return [];
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function writeJson(string $file, array $data): void{
        file_put_contents(
            $file,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }
}