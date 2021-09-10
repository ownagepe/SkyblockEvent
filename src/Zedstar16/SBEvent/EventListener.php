<?php


namespace Zedstar16\SBEvent;


use onebone\economyapi\EconomyAPI;
use pocketmine\event\block\BlockGrowEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\server\CommandEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;

class EventListener implements Listener
{
    /** @var Server */
    public $server;

    /** @var array */
    public $cooldown = [];

    public function __construct()
    {
        $this->server = Server::getInstance();
    }

    /**
     * @param PlayerJoinEvent $event
     * @priority HIGHEST
     */
    public function onJoin(PlayerJoinEvent $event)
    {
        $p = $event->getPlayer();
        if (Base::$event_stage === -1) {
            $p->teleport(Base::$instance->getServer()->getLevelByName("WaitingZone")->getSpawnLocation());
        }
        if (file_exists("worlds/{$p->getName()}")) {
            if (!$this->server->isLevelLoaded($p->getName())) {
                $this->server->loadLevel($p->getName());
            }
        }
        if (Base::$event_stage === 0) {
            Base::$instance->getScheduler()->scheduleDelayedTask(new ClosureTask(function (int $currentTick) use ($p): void {
                Server::getInstance()->dispatchCommand($p, "is");
            }), 1 * 20);
        }
    }

    public function onEntityDamage(EntityDamageEvent $event)
    {
        $entity = $event->getEntity();
        if ($entity instanceof Player) {
            $event->setCancelled(true);
        }
    }


    public function commandEvent(CommandEvent $event)
    {
        $p = $event->getSender();
        $cmd = explode(" ", $event->getCommand())[0];
        if (in_array($cmd, ["ah", "pay", "givemoney", "vote"])) {
            $p->sendMessage("§cYou cannot run this during the event");
            $event->setCancelled(true);
            return;
        }
        if (Base::$event_stage === -1 && in_array($cmd, ["kit", "pv", "enchantmenu", "shop", "echest", "ah", "pay", "spawn", "hub", "warp"])) {
            $p->sendMessage("§cYou cannot run this command before the event has started");
            $event->setCancelled(true);
        }

    }

    public function onInteract(PlayerInteractEvent $event)
    {
        $p = $event->getPlayer();
        $username = $p->getName();
        $item = $event->getItem();
        if (!isset($this->cooldown[$username]) || (time() - $this->cooldown[$username]) > 1) {
            if ($item->getNamedTag()->hasTag("sellstick")) {
                $this->server->dispatchCommand($p, "sell all");
                $this->cooldown[$username] = time();
            }
        }
    }

    public function giveSellStick(Player $p, $slot)
    {

        $item = ItemFactory::get(ItemIds::STICK);
        $item->setCustomName("§r§b§lSell Stick\n§7Tap to sell all items");
        $nbt = $item->getNamedTag();
        $nbt->setString("sellstick", "sellstick");
        $item->setNamedTag($nbt);
        $p->getInventory()->setItem($slot, $item);
    }

    public function onLevelChange(EntityLevelChangeEvent $event)
    {
        $p = $event->getEntity();
        if ($p instanceof Player) {
            if ($event->getTarget()->getName() === $p->getName()) {
                $inventory = $p->getInventory();
                $slot = $inventory->getItem(9);
                if ($slot->getId() === Item::AIR) {
                    $this->giveSellStick($p, 8);
                    $p->sendMessage(Base::prefix . "Sell stick placed in your 9th hotbar slot");
                } else {
                    for ($i = 0; $i < 4 * 9; $i++) {
                        $item = $inventory->getItem($i);
                        if ($item->getId() === Item::AIR) {
                            $this->giveSellStick($p, $i);
                            $p->sendMessage(Base::prefix . "Sell stick placed in inventory slot: §f" . $i . "§7 as far right hotbar slot was taken");
                            break;
                        }
                    }
                }
            } else {
                foreach ($p->getInventory()->getContents() as $item) {
                    if ($item->getNamedTag()->hasTag("sellstick")) {
                        $p->getInventory()->remove($item);
                    }
                }
            }
        }
    }
}