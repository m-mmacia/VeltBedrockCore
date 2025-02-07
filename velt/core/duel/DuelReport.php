<?php

namespace velt\core\duel;

use pocketmine\player\Player;

class DuelReport {

    private $player1;
    private $player2;
    private $winner;

    public function __construct(Player $player1, Player $player2) {
        $this->player1 = $player1;
        $this->player2 = $player2;
    }

    public function getPlayer1(): Player {
        return $this->player1;
    }

    public function getPlayer2(): Player {
        return $this->player2;
    }

    public function getWinner(): ?Player {
        return $this->winner;
    }

    public function setWinner(Player $winner): void {
        $this->winner = $winner;
    }

    public function getLoser(): Player {
        return $this->player1 === $this->winner ? $this->player2 : $this->player1;
    }

    public function getReport(): string {
        $winnerName = $this->winner ? $this->winner->getName() : "Unknown";
        $loserName = $this->getLoser()->getName();
        return "Winner: $winnerName ()\nLoser: $loserName ()";
    }
}
