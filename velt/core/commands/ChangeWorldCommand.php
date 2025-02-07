<?php

namespace velt\core\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\world\World;
use pocketmine\Server;

class ChangeWorldCommand extends Command {

    public function __construct() {
        parent::__construct("changeworld", "Change world of targeted player");
        $this->setPermission("velt.changeworld");
    }

    public function execute(CommandSender $sender, string $label, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage("Only command for player");
            return false;

        if (count($args) !== 1) {
            $sender->sendMessage("Usage: /changeworld <nomDuMonde>");
            return false;
        }

        $worldName = array_shift($args);
        $world = Server::getInstance()->getWorldManager()->getWorldByName($worldName);

        if ($world instanceof World) {
            $sender->teleport($world->getSpawnLocation());
            $sender->sendMessage("teleported to " . $world->getFolderName());
        } else {
            $sender->sendMessage("World not found");
        }

        return true;
    }
}
