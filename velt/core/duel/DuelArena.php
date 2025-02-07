<?php

namespace velt\core\duel;

use pocketmine\world\World;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

class DuelArena {
    private $world;
    private $spawn1;
    private $spawn2;
    private $name;
    private $available = true;
    private $player1 = null;
    private $player2 = null;

    public function __construct(World $world, Vector3 $spawn1, Vector3 $spawn2, $name) {
        $this->world = $world;
        $this->spawn1 = $spawn1;
        $this->spawn2 = $spawn2;
        $this->name = $name;
    }

    public function getName() {
        return $this->name;
    }

    public function isAvailable() {
        return $this->available;
    }

    public function prepare(Player $player1, Player $player2): void {
        $this->available = false;
        $this->player1 = $player1;
        $this->player2 = $player2;
    }

    public function start(Player $player1, Player $player2): void {
        $player1->teleport($this->world->getSafeSpawn($this->spawn1));
        $player2->teleport($this->world->getSafeSpawn($this->spawn2));
    }

    public function reset(): void {
        $this->available = true;
        $this->player1 = null;
        $this->player2 = null;
    }

    public function isPlayerInArena(Player $player): bool {
        return in_array($player, [$this->player1, $this->player2]);
    }

    public function getOpponent(Player $player): ?Player {
        if ($this->player1 === $player) {
            return $this->player2;
        } elseif ($this->player2 === $player) {
            return $this->player1;
        }
        return null;
    }
}
