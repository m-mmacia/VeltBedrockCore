<?php

namespace velt\core\anticheat;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\player\Player;
use velt\core\Main;
use velt\core\anticheat\WebhookLogger;

class AntiAutoClickListener implements Listener {

    private $plugin;
    private $clickCounts = [];
    private $lastClickTimes = [];
    private $clickLimit = 20; //max click per sec - re check
    private $alertLimit = 5; //nb of alerts

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->plugin->getServer()->getPluginManager()->registerEvents($this, $plugin);
    }

    public function onPlayerInteract(PlayerInteractEvent $event): void {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        $currentTime = microtime(true);

        if (!isset($this->clickCounts[$playerName])) {
            $this->clickCounts[$playerName] = 0;
            $this->lastClickTimes[$playerName] = $currentTime;
        }

        $timeElapsed = $currentTime - $this->lastClickTimes[$playerName];

        if ($timeElapsed > 1) {
            $this->clickCounts[$playerName] = 0;
            $this->lastClickTimes[$playerName] = $currentTime;
        }

        $this->clickCounts[$playerName]++;

        if ($this->clickCounts[$playerName] > $this->clickLimit) {
            $this->alertCounts[$playerName] = ($this->alertCounts[$playerName] ?? 0) + 1;

            if ($this->alertCounts[$playerName] >= $this->alertLimit) {
                $player->kick("Auto-click detected", false);
                $ping = $player->getNetworkSession()->getPing();
                WebhookLogger::sendAlert("[LEON] **{$playerName}** was kicked for auto-clicking. Ping: {$ping}ms");
                unset($this->alertCounts[$playerName]);
            } else {
                $ping = $player->getNetworkSession()->getPing();
                WebhookLogger::sendAlert("[LEON] Auto-click alert for player **{$playerName}**. Alert count: {$this->alertCounts[$playerName]}. Ping: {$ping}ms");
            }
        }
    }
}
