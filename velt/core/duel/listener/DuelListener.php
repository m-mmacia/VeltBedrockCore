<?php

namespace velt\core\duel\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\player\Player;
use velt\core\Main;
use velt\core\duel\DuelManager;

class DuelListener implements Listener {

    private $plugin;
    private $duelManager;

    public function __construct(Main $plugin, DuelManager $duelManager) {
        $this->plugin = $plugin;
        $this->duelManager = $duelManager;
        $this->plugin->getServer()->getPluginManager()->registerEvents($this, $plugin);
    }

    public function onPlayerQuit(PlayerQuitEvent $event): void {
        $player = $event->getPlayer();
        $this->handlePlayerDisconnection($player);
    }

    public function onPlayerDeath(PlayerDeathEvent $event): void {
        $player = $event->getEntity();
        if ($player instanceof Player) {
            $this->handlePlayerDisconnection($player);
        }
    }

    public function onPlayerMove(PlayerMoveEvent $event): void {
        $player = $event->getPlayer();
        if ($this->duelManager->isPlayerInDuel($player) && $event->getTo()->getWorld() !== $event->getFrom()->getWorld()) {
            $this->handlePlayerDisconnection($player);
        }
    }

    private function handlePlayerDisconnection(Player $player): void {
        $duel = $this->duelManager->getPlayerDuel($player);
        if ($duel !== null) {
            $this->duelManager->handlePlayerForfeit($player, $duel);
        }
    }

    public function onEntityDamage(EntityDamageEvent $event): void {
        $entity = $event->getEntity();

        if ($entity instanceof Player) {
            if ($event->getFinalDamage() >= $entity->getHealth()) {
                $event->cancel();

                $duel = $this->duelManager->getPlayerDuel($entity);
                if ($duel !== null) {
                    $this->duelManager->handlePlayerForfeit($entity, $duel);
                }
            }
        }
    }
}
