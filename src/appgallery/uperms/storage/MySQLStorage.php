<?php

declare(strict_types=1);

namespace appgallery\uperms\storage;

use appgallery\uperms\group\Group;
use appgallery\uperms\group\GroupSerializer;
use mysqli;
use pocketmine\utils\TextFormat;

final class MySQLStorage implements IStorage {

    private mysqli $mysqli;

    public function __construct(
        private readonly string $host,
        private readonly int    $port,
        private readonly string $user,
        private readonly string $password,
        private readonly string $database,
    ) {}

    public function init(): void {
        $this->mysqli = new mysqli(
            $this->host,
            $this->user,
            $this->password,
            $this->database,
            $this->port,
        );

        if ($this->mysqli->connect_errno !== 0) {
            throw new \RuntimeException(
                "[UltimatePerms] MySQL connection failed: {$this->mysqli->connect_error}"
            );
        }

        $this->mysqli->set_charset('utf8mb4');
        $this->createTables();

        \GlobalLogger::get()->info(
            TextFormat::GRAY .
            "[UltimatePerms] Storage: MySQL @ {$this->host}:{$this->port}/{$this->database}"
        );
    }

    private function createTables(): void {
        $this->mysqli->query("
            CREATE TABLE IF NOT EXISTS up_groups (
                id        VARCHAR(64)  PRIMARY KEY NOT NULL,
                data      MEDIUMTEXT               NOT NULL,
                updatedAt TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $this->mysqli->query("
            CREATE TABLE IF NOT EXISTS up_players (
                xuid      VARCHAR(32)  PRIMARY KEY NOT NULL,
                username  VARCHAR(32)              NOT NULL,
                data      MEDIUMTEXT               NOT NULL,
                updatedAt TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    // ── Grupos ────────────────────────────────────────────────────────

    public function loadGroups(): array {
        $result = $this->mysqli->query("SELECT id, data FROM up_groups;");
        $groups = [];

        if ($result === false) {
            return [];
        }

        while ($row = $result->fetch_assoc()) {
            $raw = json_decode((string) $row['data'], true);

            if (!is_array($raw)) {
                continue;
            }

            try {
                $groups[] = GroupSerializer::fromYaml((string) $row['id'], $raw);
            } catch (\Throwable $e) {
                \GlobalLogger::get()->error(
                    "[UltimatePerms] Failed to load group '{$row['id']}': {$e->getMessage()}"
                );
            }
        }

        return $groups;
    }

    public function saveGroup(Group $group): void {
        $stmt = $this->mysqli->prepare("
            INSERT INTO up_groups (id, data)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE data = VALUES(data);
        ");

        $id   = $group->getId();
        $data = json_encode(GroupSerializer::toYaml($group));

        $stmt->bind_param('ss', $id, $data);
        $stmt->execute();
        $stmt->close();
    }

    public function deleteGroup(string $id): void {
        $stmt = $this->mysqli->prepare("DELETE FROM up_groups WHERE id = ?;");
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $stmt->close();
    }

    // ── Jugadores ─────────────────────────────────────────────────────

    public function loadPlayer(string $xuid): ?array {
        $stmt = $this->mysqli->prepare(
            "SELECT data FROM up_players WHERE xuid = ?;"
        );

        $stmt->bind_param('s', $xuid);
        $stmt->execute();

        $result = $stmt->get_result();
        $row    = $result->fetch_assoc();
        $stmt->close();

        if ($row === null) {
            return null;
        }

        $data = json_decode((string) $row['data'], true);
        return is_array($data) ? $data : null;
    }

    public function savePlayer(array $data): void {
        $stmt = $this->mysqli->prepare("
            INSERT INTO up_players (xuid, username, data)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                username = VALUES(username),
                data     = VALUES(data);
        ");

        $xuid     = (string) $data['xuid'];
        $username = (string) $data['username'];
        $json     = json_encode($data);

        $stmt->bind_param('sss', $xuid, $username, $json);
        $stmt->execute();
        $stmt->close();
    }

    public function deletePlayer(string $xuid): void {
        $stmt = $this->mysqli->prepare(
            "DELETE FROM up_players WHERE xuid = ?;"
        );

        $stmt->bind_param('s', $xuid);
        $stmt->execute();
        $stmt->close();
    }

    public function loadPlayerByName(string $username): ?array {
        $stmt = $this->mysqli->prepare(
            "SELECT data FROM up_players WHERE LOWER(username) = LOWER(?);"
        );

        $stmt->bind_param('s', $username);
        $stmt->execute();

        $result = $stmt->get_result();
        $row    = $result->fetch_assoc();
        $stmt->close();

        if ($row === null) {
            return null;
        }

        $data = json_decode((string) $row['data'], true);
        return is_array($data) ? $data : null;
    }

    public function close(): void {
        $this->mysqli->close();
    }
}