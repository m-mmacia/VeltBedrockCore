<?php

namespace velt\core\listeners;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\player\Player;
use velt\core\Main;

class ChatListener implements Listener {

    private $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function onPlayerChat(PlayerChatEvent $event): void {
        $event->cancel();
        $player = $event->getPlayer();
        $message = $event->getMessage();

        $dbManager = $this->plugin->getDatabaseManager();
        $rank = $dbManager->getPlayerRank($player->getName());

        $rankColor = "§7";
        switch ($rank) {
            case 'OWNER':
                $rankColor = "§c"; // Red
                break;
            case 'MODERATOR':
                $rankColor = "§5"; // Aqua
                break;
            case 'GOLD':
                $rankColor = "§6"; // Gold
                break;
            case 'MEMBER':
                $rankColor = "§a"; // Green
                break;
        }

        $formattedMessage = "§l{$rankColor}{$rank} §r{$rankColor}{$player->getName()}: §f{$message}";

        foreach ($this->plugin->getServer()->getOnlinePlayers() as $onlinePlayer) {
            $onlinePlayer->sendMessage($formattedMessage);
        }
    }
}
