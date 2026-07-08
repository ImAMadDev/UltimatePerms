<?php

declare(strict_types=1);

namespace appgallery\uperms\storage;

use appgallery\uperms\group\Group;
use appgallery\uperms\group\GroupSerializer;
use pocketmine\utils\TextFormat;

final class SQLiteStorage implements IStorage{

    private \SQLite3 $db;

    public function __construct(private readonly string $dataPath){
    }

    public function init(): void{
        if(!is_dir($this->dataPath)){
            mkdir($this->dataPath, 0755, true);
        }

        $this->db = new \SQLite3($this->dataPath . 'ultimateperms.db');
        $this->db->enableExceptions(true);
        $this->db->exec('PRAGMA journal_mode = WAL;');   // Mejor concurrencia
        $this->db->exec('PRAGMA synchronous  = NORMAL;');

        $this->createTables();

        \GlobalLogger::get()->info(
            TextFormat::GRAY . "[UltimatePerms] Storage: SQLite @ {$this->dataPath}ultimateperms.db"
        );
    }

    private function createTables(): void{
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS groups (
                id      TEXT PRIMARY KEY NOT NULL,
                data    TEXT             NOT NULL   -- JSON del grupo completo
            );
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS players (
                xuid     TEXT PRIMARY KEY NOT NULL,
                username TEXT             NOT NULL,
                data     TEXT             NOT NULL  -- JSON de PlayerSession::serialize()
            );
        ");
    }

    // ── Grupos ────────────────────────────────────────────────────────

    public function loadGroups(): array{
        $result = $this->db->query("SELECT id, data FROM groups;");
        $groups = [];

        while($row = $result->fetchArray(SQLITE3_ASSOC)){
            $raw = json_decode((string)$row['data'], true);

            if(!is_array($raw)){
                continue;
            }

            try{
                $groups[] = GroupSerializer::fromYaml((string)$row['id'], $raw);
            } catch(\Throwable $e){
                \GlobalLogger::get()->error(
                    "[UltimatePerms] Failed to load group '{$row['id']}': {$e->getMessage()}"
                );
            }
        }

        return $groups;
    }

    public function saveGroup(Group $group): void{
        $stmt = $this->db->prepare("
            INSERT INTO groups (id, data)
            VALUES (:id, :data)
            ON CONFLICT(id) DO UPDATE SET data = excluded.data;
        ");

        $stmt->bindValue(':id', $group->getId());
        $stmt->bindValue(':data', json_encode(GroupSerializer::toYaml($group)));
        $stmt->execute();
    }

    public function deleteGroup(string $id): void{
        $stmt = $this->db->prepare("DELETE FROM groups WHERE id = :id;");
        $stmt->bindValue(':id', $id);
        $stmt->execute();
    }

    // ── Jugadores ─────────────────────────────────────────────────────

    public function loadPlayer(string $xuid): ?array{
        $stmt = $this->db->prepare("SELECT data FROM players WHERE xuid = :xuid;");
        $stmt->bindValue(':xuid', $xuid);

        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);

        if($row === false){
            return null;
        }

        $data = json_decode((string)$row['data'], true);
        return is_array($data) ? $data : null;
    }

    public function savePlayer(array $data): void{
        $stmt = $this->db->prepare("
            INSERT INTO players (xuid, username, data)
            VALUES (:xuid, :username, :data)
            ON CONFLICT(xuid) DO UPDATE SET
                username = excluded.username,
                data     = excluded.data;
        ");

        $stmt->bindValue(':xuid', (string)$data['xuid']);
        $stmt->bindValue(':username', (string)$data['username']);
        $stmt->bindValue(':data', json_encode($data));
        $stmt->execute();
    }

    public function deletePlayer(string $xuid): void{
        $stmt = $this->db->prepare("DELETE FROM players WHERE xuid = :xuid;");
        $stmt->bindValue(':xuid', $xuid);
        $stmt->execute();
    }

    public function loadPlayerByName(string $username): ?array{
        $stmt = $this->db->prepare("SELECT data FROM players WHERE LOWER(username) = LOWER(:username);");
        $stmt->bindValue(':username', $username);

        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);

        if($row === false){
            return null;
        }

        $data = json_decode((string)$row['data'], true);
        return is_array($data) ? $data : null;
    }

    public function close(): void{
        $this->db->close();
    }
}