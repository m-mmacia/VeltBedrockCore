<?php

namespace velt\core\duel;

use pocketmine\scheduler\Task;
use pocketmine\player\Player;
use velt\core\duel\arena\DuelArena;

class StartDuelTask extends Task {
    private $player1;
    private $player2;
    private $arena;
    private $duelManager;
    private $countdown = 5;

    public function __construct(Player $player1, Player $player2, DuelArena $arena, DuelManager $duelManager) {
        $this->player1 = $player1;
        $this->player2 = $player2;
        $this->arena = $arena;
        $this->duelManager = $duelManager;
    }

    public function onRun(): void {
        if ($this->countdown > 0) {
            $this->countdown--;
        } else {
            if ($this->player1->isOnline() && $this->player2->isOnline()) {
                $this->duelManager->start($this->player1, $this->player2, $this->arena);
            }
            $this->getHandler()->cancel();
        }
    }
}
