<?php

namespace velt\core\database;

use mysqli;
use velt\core\Main;
use pocketmine\player\Player;

class DatabaseManager {
    private $conn;
    private $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->initDatabase();
    }

    private function initDatabase(): void {
        $this->conn = new mysqli('localhost', 'root', '', 'test');
        if ($this->conn->connect_error) {
            $this->plugin->getLogger()->critical("Database connection failed: " . $this->conn->connect_error);
            $this->plugin->getServer()->getPluginManager()->disablePlugin($this->plugin);
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS Players (
            Name VARCHAR(255),
            ip VARCHAR(255),
            uuid VARCHAR(255) UNIQUE,
            link BOOLEAN DEFAULT FALSE,
            discordid VARCHAR(255) DEFAULT NULL,
            xp INT DEFAULT 0,
            kills INT DEFAULT 0,
            deaths INT DEFAULT 0,
            rank VARCHAR(255) DEFAULT 'PLAYER',
            register VARCHAR(255) DEFAULT NULL
        )";

        if (!$this->conn->query($sql)) {
            $this->plugin->getLogger()->critical("Error creating Players table: " . $this->conn->error);
        } else {
            $this->plugin->getLogger()->info("Players table initialized successfully or already exists.");
        }
    }

    public function initPlayersData(Player $player) {
        $name = $player->getName();
        $ip = $player->getNetworkSession()->getIp();
        $uuid = substr(bin2hex(random_bytes(7)), 0, 7);
        $kills = 0;
        $link = 0;
        $deaths = 0;
        $registerDate = date("Y-m-d H:i:s");

        $stmt = $this->conn->prepare("SELECT * FROM Players WHERE Name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $result = $stmt->get_result();

        if($result->num_rows === 0) {
            $stmt = $this->conn->prepare("INSERT INTO Players (Name, Ip, uuid, link, discordid, kills, deaths, register) VALUES (?, ?, ?, ?, NULL, ?, ?, ?)");
            if ($stmt === false) {
                die("Error preparing statement: " . $this->conn->error);
            }
            $stmt->bind_param("sssiiis", $name, $ip, $uuid, $link, $kills, $deaths, $registerDate);
            $stmt->execute();
        } else {
            $updateStmt = $this->conn->prepare("UPDATE Players SET Ip = ? WHERE Name = ?");
            $updateStmt->bind_param("ss", $ip, $name);
            $updateStmt->execute();
        }
    }

    public function checkPlayerLinkStatus(Player $player) {
        $name = $player->getName();
        $stmt = $this->conn->prepare("SELECT link FROM Players WHERE Name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['link'] === 1 ? "§alinked" : "§cunlinked";
        }
        return "§cerror";
    }

    public function getPlayerUUID(Player $player) {
        $name = $player->getName();
        $stmt = $this->conn->prepare("SELECT uuid FROM Players WHERE Name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return "§e" . $row['uuid'];
        }
        return "§cerror";
    }

    public function getPlayerStat(string $playerName, string $stat): int {
        $stmt = $this->conn->prepare("SELECT $stat FROM Players WHERE Name = ?");
        $stmt->bind_param("s", $playerName);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return (int)$row[$stat];
        }
        return 0;
    }

    public function getPlayerRank(string $playerName): string {
        $stmt = $this->conn->prepare("SELECT rank FROM Players WHERE Name = ?");
        $stmt->bind_param("s", $playerName);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['rank'];
        }
        return "Unknown";
    }

    public function getPlayerRegisterDate(string $playerName): string {
        $stmt = $this->conn->prepare("SELECT register FROM Players WHERE Name = ?");
        $stmt->bind_param("s", $playerName);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['register'];
        }
        return "Unknown";
    }

    public function updatePlayerStats(string $playerName, string $stat, int $amount): void {
        $query = "UPDATE Players SET $stat = $stat + ? WHERE Name = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("is", $amount, $playerName);
        $stmt->execute();
    }

    public function playerExists(string $playerName): bool {
        $stmt = $this->conn->prepare("SELECT Name FROM Players WHERE Name = ?");
        $stmt->bind_param("s", $playerName);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }
}
