<?php

namespace velt\core\listeners;

use pocketmine\event\Listener;
use pocketmine\item\VanillaItems;
use pocketmine\player\GameMode;
use pocketmine\world\Position;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\world\World;
use pocketmine\player\Player;
use pocketmine\math\Vector3;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\entity\EntityDamageEvent;
use velt\core\commands\BypassCommand;
use velt\core\Main;
use velt\core\items\ItemUtils;

class PlayerEventListener implements Listener {
    
    private $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->plugin->getServer()->getPluginManager()->registerEvents($this, $plugin);
    }

    public function onJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        $dbManager = $this->plugin->getDatabaseManager();
    
        $dbManager->initPlayersData($player);
        $linkStatus = $dbManager->checkPlayerLinkStatus($player);
        $uuid = $dbManager->getPlayerUUID($player);
    
        $event->setJoinMessage(false);
        $player->sendMessage("§7Welcome to VELT " . $player->getName() . "\n§7Discord : " . $linkStatus . "\n§7UUID : " . $uuid);
    
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getEffects()->clear();
        $player->setHealth(20);
        $player->getHungerManager()->setFood(20);
        $player->getHungerManager()->setSaturation(20);
        $player->setAllowFlight(false);
        $player->setFlying(false);
        $player->setGamemode(GameMode::SURVIVAL());
    
        ItemUtils::giveSpawnItems($player);
    
        $world = $player->getServer()->getWorldManager()->getWorldByName("world");
        if ($world instanceof World) {
            $spawnPosition = new Position(49, 28, 64, $world);
            $player->teleport($spawnPosition);
        }

        $uuidBypass = $player->getUniqueId()->toString();
        unset(BypassCommand::$bypassStates[$uuidBypass]);
        
        $this->updatePlayerNameTag($player);
    }

    public function onPlayerRespawn(PlayerRespawnEvent $event): void {
        $player = $event->getPlayer();
        $this->updatePlayerNameTag($player);
    }

    private function updatePlayerNameTag(Player $player): void {
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
            case 'VIP':
                $rankColor = "§6"; // Gold
                break;
            case 'MEMBER':
                $rankColor = "§a"; // Green
                break;
        }

        $device = $player->getNetworkSession()->getPlayerInfo()->getExtraData()["DeviceOS"] ?? 0;
        $deviceName = "Unknown";
        switch ($device) {
            case 1:
                $deviceName = "Android";
                break;
            case 2:
                $deviceName = "iOS";
                break;
            case 3:
                $deviceName = "macOS";
                break;
            case 4:
                $deviceName = "FireOS";
                break;
            case 5:
                $deviceName = "GearVR";
                break;
            case 6:
                $deviceName = "HoloLens";
                break;
            case 7:
                $deviceName = "Windows 10";
                break;
            case 8:
                $deviceName = "Windows 32";
                break;
            case 9:
                $deviceName = "Dedicated";
                break;
            case 10:
                $deviceName = "TVOS";
                break;
            case 11:
                $deviceName = "PlayStation";
                break;
            case 12:
                $deviceName = "Switch";
                break;
            case 13:
                $deviceName = "Xbox";
                break;
            case 14:
                $deviceName = "Windows Phone";
                break;
        }

        $nameTag = "§l{$rankColor}{$rank} §r{$rankColor}{$player->getName()}\n§7[{$deviceName}]";
        $player->setNameTag($nameTag);
        $player->setNameTagAlwaysVisible(true);
    }
    
    public function onBlockBreak(BlockBreakEvent $event): void {
        $player = $event->getPlayer();
        $world = $player->getWorld()->getFolderName();
        if ($world === "world" && !BypassCommand::hasPlayerBypass($player->getUniqueId()->toString())) {
            $event->cancel();
            $player->sendMessage("§cYou do not have permission.");
        }
    }

    public function onBlockPlace(BlockPlaceEvent $event): void {
        $player = $event->getPlayer();
        $world = $player->getWorld()->getFolderName();
        if ($world === "world" && !BypassCommand::hasPlayerBypass($player->getUniqueId()->toString())) {
            $event->cancel();
            $player->sendMessage("§cYou do not have permission.");
        }
    }

    public function onDamage(EntityDamageEvent $event): void {
        $entity = $event->getEntity();
        if ($entity instanceof Player && $entity->getWorld()->getFolderName() === "world") {
            $event->cancel();
            if ($event->getCause() === EntityDamageEvent::CAUSE_VOID) {
                $targetPosition = new Position(49, 28, 64, $entity->getWorld());
                $entity->teleport($targetPosition);
            }
        }
    }
}
