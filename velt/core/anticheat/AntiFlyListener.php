<?php

namespace velt\core\anticheat;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\Server;
use velt\core\anticheat\WebhookLogger;

class AntiFlyListener implements Listener {
    private $lastOnGround = [];
    private $lastDamage = [];
    private $alertCounts = [];
    private $alertLimit = 5;
    private $plugin;
    private $exemptBlocks = ["Slime Block", "Honey Block"];

    public function __construct($plugin) {
        $this->plugin = $plugin;
        Server::getInstance()->getPluginManager()->registerEvents($this, $plugin);
    }

    public function onPlayerMove(PlayerMoveEvent $event): void {
        $player = $event->getPlayer();
        $name = $player->getName();

        if ($this->shouldSkipCheck($player)) {
            return;
        }

        if (isset($this->lastDamage[$name]) && microtime(true) - $this->lastDamage[$name] < 3) {
            return;
        }

        $currentPos = $player->getPosition();
        $blockUnder = $player->getWorld()->getBlock($currentPos->floor()->subtract(0, 1, 0));

        if (!$player->isOnGround() && !in_array($blockUnder->getName(), $this->exemptBlocks)) {
            if (!isset($this->lastOnGround[$name])) {
                $this->lastOnGround[$name] = microtime(true);
            } elseif (microtime(true) - $this->lastOnGround[$name] > 2) {
                $this->alertCounts[$name] = ($this->alertCounts[$name] ?? 0) + 1;

                if ($this->alertCounts[$name] >= $this->alertLimit) {
                    $player->kick("Fly detected", false);
                    $ping = $player->getNetworkSession()->getPing();
                    WebhookLogger::sendAlert("[LEON] **{$name}** was kicked for flying. Ping: {$ping}ms");
                    unset($this->alertCounts[$name]);
                } else {
                    $ping = $player->getNetworkSession()->getPing();
                    WebhookLogger::sendAlert("[LEON] Flying alert for player **{$name}**. Alert count: {$this->alertCounts[$name]}. Ping: {$ping}ms");
                }
            }
        } else {
            unset($this->lastOnGround[$name]);
        }
    }

    public function onEntityDamage(EntityDamageEvent $event): void {
        $entity = $event->getEntity();
        if ($entity instanceof Player) {
            $this->lastDamage[$entity->getName()] = microtime(true);
        }
    }

    private function shouldSkipCheck(Player $player): bool {
        return $player->hasPermission("velt.bypass") ||
               $player->getGamemode() == GameMode::CREATIVE() ||
               $player->getGamemode() == GameMode::SPECTATOR() ||
               $player->getAllowFlight() ||
               $player->getNetworkSession()->getPing() > 300;
    }
}
