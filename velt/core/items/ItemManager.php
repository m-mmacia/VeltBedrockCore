<?php

namespace velt\core\items;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\VanillaItems;
use jojoe77777\FormAPI\SimpleForm;
use jojoe77777\FormAPI\CustomForm;
use pocketmine\player\Player;
use velt\core\Main;

class ItemManager implements Listener {

    private $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->plugin->getServer()->getPluginManager()->registerEvents($this, $plugin);
    }

    public function onInteract(PlayerInteractEvent $event): void {
        $player = $event->getPlayer();
        $item = $event->getItem();
        if ($item->getCustomName() === "§l§5Duel") {
            $this->plugin->getDuelManager()->openDuelMenu($player);
        }
        if ($item->equals(VanillaItems::FEATHER()->setCustomName("§cLeave Queue"), true, false)) {
            $event->cancel();
            $this->plugin->getDuelManager()->leaveQueue($player);
            $this->plugin->getDuelManager()->restoreInventory($player);
            $player->getInventory()->removeItem(VanillaItems::FEATHER()->setCustomName("§cLeave Queue"));
        }
        if ($item->getCustomName() === "§l§5Profile") {
            $this->showPlayerProfileMenu($player);
        }
    }

    private function showPlayerProfileMenu(Player $player): void {
        $name = $player->getName();
        $dbManager = $this->plugin->getDatabaseManager();
        $kills = $dbManager->getPlayerStat($name, 'kills');
        $deaths = $dbManager->getPlayerStat($name, 'deaths');
        $xp = $dbManager->getPlayerStat($name, 'xp');
        $rank = $dbManager->getPlayerRank($name);
        $lastReport = $this->plugin->getDuelManager()->getLastDuelReport($player);

        $form = new CustomForm(function (Player $player, ?array $data) {
            if ($data === null || !isset($data[1]) || trim($data[1]) === '') {
                $player->sendMessage("§cInvalid player name.");
                return;
            }

            $searchedName = trim($data[1]);
            if (!$this->plugin->getDatabaseManager()->playerExists($searchedName)) {
                $player->sendMessage("§cPlayer not found.");
                return;
            }
            $this->showOtherPlayerProfile($player, $searchedName);
        });

        $form->setTitle("§aProfile of " . $name);
        $form->addLabel("§5Rank: §7" . $rank);
        $form->addLabel("§5Kills: §7" . $kills);
        $form->addLabel("§5Deaths: §7" . $deaths);
        $form->addLabel("§5XP: §7" . $xp);

        if ($lastReport !== null) {
            $form->addLabel("§5Last Duel:\n§7" . $lastReport->getReport());
        } else {
            $form->addLabel("§5No recent duels.");
        }

        $form->addInput("§aSearch for a player", "Enter the player's name");
        $form->sendToPlayer($player);
    }

    private function showOtherPlayerProfile(Player $player, string $searchedName): void {
        $dbManager = $this->plugin->getDatabaseManager();
        $kills = $dbManager->getPlayerStat($searchedName, 'kills');
        $deaths = $dbManager->getPlayerStat($searchedName, 'deaths');
        $xp = $dbManager->getPlayerStat($searchedName, 'xp');
        $rank = $dbManager->getPlayerRank($searchedName);

        $lastReport = $this->plugin->getDuelManager()->getLastDuelReportByName($searchedName);

        $form = new SimpleForm(function (Player $player, ?int $data) {
            if ($data === null) {
                return;
            }
        });

        $form->setTitle("§aProfile of " . $searchedName);
        $form->setContent("§5Rank: §7" . $rank . "\n§5Kills: §7" . $kills . "\n§5Deaths: §7" . $deaths . "\n§5XP: §7" . $xp . "\n");

        if ($lastReport !== null) {
            $form->addButton("§5Last Duel:\n§7" . $lastReport->getReport());
        } else {
            $form->addButton("§5No recent duels.");
        }

        $form->sendToPlayer($player);
    }
}
