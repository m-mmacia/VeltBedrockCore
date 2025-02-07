<?php

namespace velt\core\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\item\VanillaItems;
use pocketmine\player\GameMode as PlayerGameMode;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\world\World;
use jojoe77777\FormAPI\SimpleForm;
use velt\core\Main;

class StaffCommand extends Command implements Listener {
    private $plugin;
    private $playerStates = [];

    public function __construct(Main $plugin) {
        parent::__construct("staff", "Toggle staff mode", "/staff", []);
        $this->plugin = $plugin;
        $this->setPermission("velt.staff");
        $this->plugin->getServer()->getPluginManager()->registerEvents($this, $plugin);
    }

    public function execute(CommandSender $sender, string $label, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage("§cThis command can only be used in-game.");
            return true;
        }

        if (!$sender->hasPermission("velt.staff")) {
            $sender->sendMessage("§cYou do not have permission to use this command.");
            return true;
        }

        $uuid = $sender->getUniqueId()->toString();

        if (isset($this->playerStates[$uuid])) {
            [$inventoryContents, $health, $maxHealth, $food, $saturation] = $this->playerStates[$uuid];
            unset($this->playerStates[$uuid]);

            $sender->getInventory()->setContents($inventoryContents);
            $sender->setHealth($health);
            $sender->setMaxHealth($maxHealth);
            $sender->getHungerManager()->setFood($food);
            $sender->getHungerManager()->setSaturation($saturation);
            $sender->setGamemode(PlayerGameMode::SURVIVAL());
            $sender->setInvisible(false);
            $sender->sendMessage("§dStaff mode deactivated.");
        } else {
            $this->playerStates[$uuid] = [
                $sender->getInventory()->getContents(),
                $sender->getHealth(),
                $sender->getMaxHealth(),
                $sender->getHungerManager()->getFood(),
                $sender->getHungerManager()->getSaturation()
            ];
            $sender->getInventory()->clearAll();

            // re check th
            $infoItem = VanillaItems::COMPASS()->setCustomName("§dPlayer Info");
            $actionItem = VanillaItems::IRON_HOE()->setCustomName("§dStaff Actions");

            $sender->getInventory()->setItem(0, $infoItem);
            $sender->getInventory()->setItem(1, $actionItem);

            $sender->setGamemode(PlayerGameMode::CREATIVE());
            $sender->setInvisible(true);
            $sender->sendMessage("§dStaff mode activated.");
        }

        return true;
    }

    public function onEntityDamageByEntity(EntityDamageByEntityEvent $event): void {
        $damager = $event->getDamager();
        $target = $event->getEntity();

        if ($damager instanceof Player && $target instanceof Player) {
            $item = $damager->getInventory()->getItemInHand();

            if ($item->getCustomName() === "§dPlayer Info") {
                $event->cancel();
                $this->showPlayerInfo($damager, $target);
            }

            if ($item->getCustomName() === "§dStaff Actions") {
                $event->cancel();
                $this->showStaffActions($damager, $target);
            }
        }
    }

    private function showPlayerInfo(Player $staff, Player $target): void {
        $dbManager = $this->plugin->getDatabaseManager();
        $name = $target->getName();
        $kills = $dbManager->getPlayerStat($name, 'kills');
        $deaths = $dbManager->getPlayerStat($name, 'deaths');
        $xp = $dbManager->getPlayerStat($name, 'xp');
        $rank = $dbManager->getPlayerRank($name);
        $registerDate = $dbManager->getPlayerRegisterDate($name);

        $info = "§aPlayer Info:\n" .
                "§eName: §f$name\n" .
                "§eRank: §f$rank\n" .
                "§eKills: §f$kills\n" .
                "§eDeaths: §f$deaths\n" .
                "§eXP: §f$xp\n" .
                "§eRegistered: §f$registerDate";

        $staff->sendMessage($info);
    }

    private function showStaffActions(Player $staff, Player $target): void {
        $form = new SimpleForm(function (Player $staff, ?int $data) use ($target) {
            if ($data === null) {
                return;
            }

            switch ($data) {
                case 0:
                    $staff->sendMessage("§aMessage sent to " . $target->getName());
                    break;
                case 1:
                    $target->kill();
                    $staff->sendMessage("§c" . $target->getName() . " has been killed.");
                    break;
                case 2:
                    $target->kick("You have been kicked by a staff member.");
                    $staff->sendMessage("§c" . $target->getName() . " has been kicked.");
                    break;
                case 3:
                    $target->ban("You have been banned by a staff member.");
                    $staff->sendMessage("§c" . $target->getName() . " has been banned.");
                    break;
            }
        });

        $form->setTitle("§cStaff Actions");
        $form->addButton("§aPrivate Message");
        $form->addButton("§cKill");
        $form->addButton("§cKick");
        $form->addButton("§cBan");

        $staff->sendForm($form);
    }
}
