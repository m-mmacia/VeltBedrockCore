<?php

namespace velt\core\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use velt\core\Main;

class BypassCommand extends Command {
    public static $bypassStates = [];
    private $plugin;
    private $permission = "velt.bypass";

    public function __construct(Main $plugin) {
        parent::__construct("bypass", "Allows bypassing restrictions.", null, []);
        $this->plugin = $plugin;
        $this->setPermission($this->permission);
    }

    public function getPermissionString(): string {
        return $this->permission;
    }

    public function execute(CommandSender $sender, string $label, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage("§cOnly players can execute this command.");
            return true;
        }
        if (!$sender->hasPermission($this->getPermissionString())) {
            $sender->sendMessage("§cYou do not have permission to use this command.");
            return false;
        }
        $uuid = $sender->getUniqueId()->toString();
        if (isset(self::$bypassStates[$uuid])) {
            unset(self::$bypassStates[$uuid]);
            $sender->sendMessage("§dBypass disabled.");
        } else {
            self::$bypassStates[$uuid] = true;
            $sender->sendMessage("§dBypass enabled.");
        }

        return true;
    }
    
    public static function hasPlayerBypass(string $uuid): bool {
        return isset(self::$bypassStates[$uuid]);
    }
}
