<?php

namespace velt\core\items;

use pocketmine\player\Player;

class ItemUtils {
    public static function giveSpawnItems(Player $player): void {
        $inventory = $player->getInventory();
        $inventory->clearAll();

        $ironSword = \pocketmine\item\VanillaItems::IRON_SWORD();
        $diamondSword = \pocketmine\item\VanillaItems::DIAMOND_SWORD();
        $goldSword = \pocketmine\item\VanillaItems::GOLDEN_SWORD();
        $netherStar = \pocketmine\item\VanillaItems::NETHER_STAR();
        $netherStar->setCustomName("§l§5Profile");
        $ironSword->setCustomName("§l§5Duel");
        $diamondSword->setCustomName("§l§5Free For All");
        $goldSword->setCustomName("§l§5Duel ranked");

        $inventory->setItem(3, $ironSword);
        $inventory->setItem(4, $diamondSword);
        $inventory->setItem(5, $goldSword);
        $inventory->setItem(8, $netherStar);
    }
}
