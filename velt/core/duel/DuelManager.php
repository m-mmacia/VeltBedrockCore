<?php

namespace velt\core\duel;

use pocketmine\player\Player;
use pocketmine\player\GameMode;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\Task;
use jojoe77777\FormAPI\SimpleForm;
use pocketmine\math\Vector3;
use velt\core\database\DatabaseManager;
use velt\core\items\ItemUtils;

class DuelManager {

    private $plugin;
    private $queue = [];
    private $arenas = [];
    private $playerStates = [];
    private $playerInventories = [];
    private $databaseManager;
    private $queueCounters = [];
    private $duelReports = [];
    private $activeDuels = [];

    public function __construct($plugin) {
        $this->plugin = $plugin;
        $this->databaseManager = $plugin->getDatabaseManager();
        $worldManager = $this->plugin->getServer()->getWorldManager();
        if (!$worldManager->isWorldLoaded("arenas")) {
            $worldManager->loadWorld("arenas");
        }
        $world = $worldManager->getWorldByName("arenas");
        if ($world !== null) {
            $this->arenas[] = new DuelArena($world, new Vector3(83, 46, 357), new Vector3(84, 48, 460), "Stony Shore");
            $this->arenas[] = new DuelArena($world, new Vector3(100, 50, 300), new Vector3(105, 50, 305), "Mountain Peak");
            $this->arenas[] = new DuelArena($world, new Vector3(200, 60, 400), new Vector3(205, 60, 405), "Desert Dunes");
            $this->plugin->getLogger()->info("Arenas added: " . count($this->arenas));
        } else {
            $this->plugin->getLogger()->info("World 'arenas' not found.");
        }
    }

    public function isPlayerInDuel(Player $player): bool {
        foreach ($this->arenas as $arena) {
            if ($arena->isPlayerInArena($player)) {
                return true;
            }
        }
        return false;
    }

    public function getPlayerDuel(Player $player): ?DuelArena {
        foreach ($this->arenas as $arena) {
            if ($arena->isPlayerInArena($player)) {
                return $arena;
            }
        }
        return null;
    }

    private function savePlayerState(Player $player): void {
        $this->playerStates[$player->getName()] = [
            'inventory' => clone $player->getInventory(),
            'health' => $player->getHealth(),
            'food' => $player->getHungerManager()->getFood(),
        ];
        $player->getInventory()->clearAll();
    }

    private function restorePlayerState(?Player $player): void {
        if ($player === null || !$player->isOnline()) {
            return;
        }

        if (isset($this->playerStates[$player->getName()])) {
            $state = $this->playerStates[$player->getName()];
            $player->getInventory()->setContents($state['inventory']->getContents());
            $player->setHealth($state['health']);
            $player->getHungerManager()->setFood($state['food']);
            unset($this->playerStates[$player->getName()]);
        }
    }

    private function saveAndClearInventory(Player $player): void {
        $this->playerInventories[$player->getName()] = $player->getInventory()->getContents();
        $player->getInventory()->clearAll();
    }

    public function restoreInventory(?Player $player): void {
        if ($player === null || !$player->isOnline()) {
            return;
        }

        if (isset($this->playerInventories[$player->getName()])) {
            $player->getInventory()->setContents($this->playerInventories[$player->getName()]);
            unset($this->playerInventories[$player->getName()]);
        }
    }

    private function giveLeaveQueueItem(Player $player): void {
        $item = \pocketmine\item\VanillaItems::FEATHER();
        $item->setCustomName("§cLeave Queue");
        $player->getInventory()->setItem(4, $item);
    }

    private function checkForOpponent(): void {
        if (count($this->queue) >= 2) {
            $this->startDuel(...array_splice($this->queue, 0, 2));
        }
    }

    private function startDuel(Player ...$players): void {
        foreach ($players as $player) {
            unset($this->queue[$player->getName()]);
        }

        $arena = $this->findAvailableArena();
        if ($arena !== null) {
            $arena->prepare(...$players);

            $duelReport = new DuelReport($players[0], $players[1]);
            $this->duelReports[spl_object_hash($arena)] = $duelReport;

            foreach ($players as $currentPlayer) {
                $opponent = current(array_filter($players, function($player) use ($currentPlayer) {
                    return $player !== $currentPlayer;
                }));

                if($opponent instanceof Player) {
                    $opponentName = $opponent->getName();
                    $currentPlayer->sendMessage("§eOpponent found: " . $opponentName . ". Duel in " . $arena->getName());
                    $sound = new \pocketmine\world\sound\FizzSound();
                    $currentPlayer->getWorld()->addSound($currentPlayer->getPosition(), $sound);
                }
            }

            $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($players, $arena) {
                $countdown = 5;
                for ($i = 0; $i < $countdown; $i++) {
                    $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($players, $i, $countdown) {
                        foreach ($players as $player) {
                            $message = "§eDuel starts in " . ($countdown - $i) . " seconds...";
                            $player->sendMessage($message);
                            $sound = new \pocketmine\world\sound\ClickSound();
                            $player->getWorld()->addSound($player->getPosition(), $sound);
                        }
                    }), 20 * $i);
                }

                $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($arena, $players) {
                    $arena->start(...$players);
                }), 20 * $countdown);
            }), 40);
        }
    }

    private function findAvailableArena(): ?DuelArena {
        $availableArenas = array_filter($this->arenas, function($arena) {
            return $arena->isAvailable();
        });

        if (empty($availableArenas)) {
            return null;
        }

        return $availableArenas[array_rand($availableArenas)];
    }

    public function handleQueueToggle(Player $player): void {
        $key = array_search($player, $this->queue, true);

        if($key !== false) {
            unset($this->queue[$key]);
            $this->queue = array_values($this->queue);
            $player->sendMessage("You have been removed from the duel queue.");
            $this->restoreInventory($player);
            $this->stopQueueCounter($player);
        } else {
            $this->saveAndClearInventory($player);
            $this->giveLeaveQueueItem($player);
            $this->queue[] = $player;
            $player->sendMessage("§aYou have been added to the duel queue.");
            $this->startQueueCounter($player);
            $this->checkForOpponent();
        }
    }

    public function openDuelMenu(Player $player): void {
        $queueCount = count($this->queue);
        $form = new SimpleForm(function (Player $player, ?int $data) {
            if ($data === null) {
                return;
            }

            switch ($data) {
                case 0:
                    $this->handleQueueToggle($player);
                    break;
            }
        });

        $form->setTitle("1v1 Menu");
        $form->addButton("Join Queue (In queue: $queueCount)");
        $player->sendForm($form);
    }

    public function leaveQueue(Player $player): void {
        if(($key = array_search($player, $this->queue)) !== false) {
            unset($this->queue[$key]);
            $this->queue = array_values($this->queue);
            $player->sendMessage("§cYou have left the duel queue.");
            $this->stopQueueCounter($player);
        }
    }

    public function handlePlayerForfeit(Player $player, DuelArena $arena): void {
        $opponent = $arena->getOpponent($player);
        if ($opponent instanceof Player) {
            $opponent->sendTitle("§aYou Win!", "", 10, 70, 20);
            $player->sendTitle("§cYou Lose!", "", 10, 70, 20);
            $this->rewardPlayer($opponent);

            $duelReport = $this->duelReports[spl_object_hash($arena)] ?? null;
            if ($duelReport) {
                $duelReport->setWinner($opponent);
                $this->showDuelReport($duelReport);
                unset($this->duelReports[spl_object_hash($arena)]);
            }

            $this->setSpectatorMode($opponent);
            $this->setSpectatorMode($player);

            $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($opponent, $player) {
                if ($opponent->isOnline()) {
                    $this->resetPlayerStatsOnly($opponent);
                    $this->setSurvivalMode($opponent);
                    $opponent->teleport($this->plugin->getServer()->getWorldManager()->getDefaultWorld()->getSafeSpawn());
                    ItemUtils::giveSpawnItems($opponent);
                }
                if ($player->isOnline()) {
                    $this->resetPlayer($player);
                    $this->setSurvivalMode($player);
                    $player->teleport($this->plugin->getServer()->getWorldManager()->getDefaultWorld()->getSafeSpawn());
                    ItemUtils::giveSpawnItems($player);
                }
            }), 100); // 5 sec delay
        }

        $arena->reset();
    }

    private function rewardPlayer(Player $player): void {
        $xp = rand(1, 5);
        $this->databaseManager->updatePlayerStats($player->getName(), 'kills', 1);
        $this->databaseManager->updatePlayerStats($player->getName(), 'xp', $xp);
        $player->sendMessage("§aYou have been awarded $xp XP and 1 kill point.");
    }

    private function resetPlayer(?Player $player): void {
        if ($player === null || !$player->isOnline()) {
            return;
        }

        $player->getInventory()->clearAll();
        $player->setHealth($player->getMaxHealth());
        $player->getHungerManager()->setFood(20);
        $this->restoreInventory($player);
        $this->databaseManager->updatePlayerStats($player->getName(), 'deaths', 1);
    }

    private function resetPlayerStatsOnly(?Player $player): void {
        if ($player === null || !$player->isOnline()) {
            return;
        }

        $player->getInventory()->clearAll();
        $player->setHealth($player->getMaxHealth());
        $player->getHungerManager()->setFood(20);
        $this->restoreInventory($player);
    }

    private function setSpectatorMode(Player $player): void {
        $player->setGamemode(GameMode::SPECTATOR());
    }

    private function setSurvivalMode(Player $player): void {
        $player->setGamemode(GameMode::SURVIVAL());
    }

    public function startQueueCounter(Player $player): void {
        $startTime = time();

        if (isset($this->queueCounters[$player->getName()])) {
            $this->queueCounters[$player->getName()]->cancel();
            unset($this->queueCounters[$player->getName()]);
        }

        $task = new class($this, $player, $startTime) extends Task {
            private $manager;
            private $player;
            private $startTime;

            public function __construct($manager, $player, $startTime) {
                $this->manager = $manager;
                $this->player = $player;
                $this->startTime = $startTime;
            }

            public function onRun(): void {
                if (!$this->manager->isPlayerInQueue($this->player)) {
                    $this->player->sendPopup("");
                    $this->manager->stopQueueCounter($this->player);
                    return;
                }

                $elapsedTime = time() - $this->startTime;
                $estimatedWaitTime = $this->manager->calculateEstimatedWaitTime();

                $this->player->sendPopup("Time in queue: " . $elapsedTime . "s\nEstimated wait time: " . $estimatedWaitTime . "s");
            }
        };

        $this->queueCounters[$player->getName()] = $this->plugin->getScheduler()->scheduleRepeatingTask($task, 20);
    }

    public function stopQueueCounter(Player $player): void {
        if (isset($this->queueCounters[$player->getName()])) {
            $this->queueCounters[$player->getName()]->cancel();
            unset($this->queueCounters[$player->getName()]);
        }
    }

    public function calculateEstimatedWaitTime(): int {
        $onlinePlayers = count($this->plugin->getServer()->getOnlinePlayers());
        $queueLength = count($this->queue);

        if ($queueLength == 0 || $onlinePlayers == 0) {
            return 0;
        }

        return max(1, intval(($queueLength / ($onlinePlayers + 1)) * 10));
    }

    public function isPlayerInQueue(Player $player): bool {
        return in_array($player, $this->queue, true);
    }

    private function showDuelReport(DuelReport $report): void {
        foreach ([$report->getWinner(), $report->getLoser()] as $player) {
            if ($player->isOnline()) {
                $player->sendMessage($report->getReport());
            }
        }
    }

    public function getLastDuelReport(Player $player): ?DuelReport {
        foreach ($this->duelReports as $report) {
            if ($report->getWinner() === $player || $report->getLoser() === $player) {
                return $report;
            }
        }
        return null;
    }

    public function getLastDuelReportByName(string $playerName): ?DuelReport {
        foreach ($this->duelReports as $report) {
            if ($report->getWinner()->getName() === $playerName || $report->getLoser()->getName() === $playerName) {
                return $report;
            }
        }
        return null;
    }
}
