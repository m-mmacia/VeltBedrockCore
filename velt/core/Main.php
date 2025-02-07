<?php

namespace velt\core;

use pocketmine\plugin\PluginBase;
use velt\core\database\DatabaseManager;
use velt\core\listeners\PlayerEventListener;
use velt\core\listeners\ChatListener;
use velt\core\commands\BypassCommand;
use velt\core\commands\StaffCommand;
use velt\core\anticheat\AntiCheatManager;
use velt\core\commands\ChangeWorldCommand;
use velt\core\items\ItemManager;
use velt\core\duel\DuelManager;

class Main extends PluginBase {
    
    /** @var DatabaseManager */
    private $databaseManager;
    /** @var DuelManager */
    private $duelManager;
    private $itemManager;
    
    public function onEnable(): void {
        $this->databaseManager = new DatabaseManager($this);
        $this->duelManager = new DuelManager($this);
        $this->itemManager = new ItemManager($this);

        $this->getServer()->getPluginManager()->registerEvents(new PlayerEventListener($this), $this);
        $this->getServer()->getPluginManager()->registerEvents(new ChatListener($this), $this);
        $this->getServer()->getCommandMap()->register("bypass", new BypassCommand($this));
        $this->getServer()->getCommandMap()->register("staff", new StaffCommand($this));
        $this->getServer()->getCommandMap()->register("changeworld", new ChangeWorldCommand($this));
        new AntiCheatManager($this);
        $this->setPermanentNight();
        $this->getLogger()->info("Core enabled");

        $worldsToLoad = ['arenas'];

        foreach ($worldsToLoad as $worldName) {
            if (!$this->getServer()->getWorldManager()->isWorldLoaded($worldName)) {
                $this->getServer()->getWorldManager()->loadWorld($worldName);
                if ($this->getServer()->getWorldManager()->isWorldLoaded($worldName)) {
                    $this->getLogger()->info("World {$worldName} has been successfully loaded.");
                } else {
                    $this->getLogger()->warning("Unable to load world {$worldName}.");
                }
            } else {
                $this->getLogger()->info("World {$worldName} is already loaded.");
            }
        }
    }
    
    public function getDatabaseManager(): DatabaseManager {
        return $this->databaseManager;
    }
    
    public function getDuelManager(): DuelManager {
        return $this->duelManager;
    }
    
    public function getItemManager(): ItemManager {
        return $this->itemManager;
    }

    private function setPermanentNight(): void {
        foreach ($this->getServer()->getWorldManager()->getWorlds() as $world) {
            $world->setTime(18000); 
            $world->stopTime();
        }
    }
}
