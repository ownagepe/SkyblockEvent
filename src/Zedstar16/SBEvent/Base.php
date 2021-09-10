<?php

declare(strict_types=1);

namespace Zedstar16\SBEvent;

use jojoe77777\FormAPI\SimpleForm;
use onebone\economyapi\EconomyAPI;
use pocketmine\block\Crops;
use pocketmine\level\generator\GeneratorManager;
use pocketmine\nbt\BigEndianNBTStream;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use Zedstar16\SBEvent\generator\VoidGenerator;
use Zedstar16\SBEvent\tasks\AsyncIslandGeneratorTask;
use Zedstar16\SBEvent\tasks\AsyncMoneyUpdateTask;
use Zedstar16\SBEvent\tasks\PlayerExecTask;
use Zedstar16\SBEvent\tasks\UpdateScoreboardTask;

class Base extends PluginBase
{
    /** @var Base */
    public static $instance = null;
    /** @var int */
    public static $event_stage = -1;
    /** @var int */
    public static $heartbeat = 0;

    /** @var array */
    public static $topmoney = [];

    public static $given_money = [];

    public const WAITING_TIME = 10*60;
    public const EVENT_DURATION = 60 * 60;

    public const prefix = "§r§8§l(§bEVENT§8)§r§7 ";

    public function onLoad()
    {
        GeneratorManager::addGenerator(VoidGenerator::class, "void", true);
    }

    public function onEnable(): void
    {
        self::$instance = $this;
        $this->getServer()->loadLevel("WaitingZone");
        $this->getLogger()->info("Hello World!");
        $this->getServer()->getPluginManager()->registerEvents(new EventListener(), $this);
        $path = $this->getDataFolder() . "cache.json";
        $this->startTicker();
        if (file_exists($path)) {
            $data = json_decode(file_get_contents($path), true);
            if (isset($data["heartbeat"])) {
                self::$heartbeat = $data["heartbeat"];
                self::$event_stage = 0;
            }
        }
    }

    public function startTicker()
    {
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(
            function (int $currentTick): void {
                self::$heartbeat++;

                switch (self::$heartbeat) {
                    case 0:
                        self::$event_stage = -1;
                        break;
                    case self::WAITING_TIME:
                        self::$event_stage = 0;
                        break;
                    case self::EVENT_DURATION + self::WAITING_TIME:
                        self::$event_stage = 1;
                        break;
                    case self::WAITING_TIME + 5:
                        foreach (Server::getInstance()->getOnlinePlayers() as $p) {
                            $this->sendIslandMenu($p);
                        }
                        break;
                }
                self::$instance->getScheduler()->scheduleRepeatingTask(new UpdateScoreboardTask(), 1);
                if (in_array(self::$event_stage, [-1, 0])) {
                    if (self::$heartbeat === self::WAITING_TIME) {
                        self::$event_stage = 0;
                        $level = $this->getServer()->getLevelByName("skyblockspawn");
                        Utils::execAll(function ($player) use ($level) {
                            $player->teleport($level->getSpawnLocation());
                        });
                    }
                    if (self::$heartbeat % 5 === 0) {
                        $this->getServer()->getAsyncPool()->submitTask(new AsyncMoneyUpdateTask(EconomyAPI::getInstance()->getAllMoney()));
                    }
                    if (self::$heartbeat % 10 === 0) {
                        Utils::execAll(function (Player $p){
                            $p->getLevel()->setTime(0);
                        });
                    }
                }

                if (self::$event_stage === 1) {
                    $end_time = self::EVENT_DURATION + self::WAITING_TIME + 15;
                    if (self::$heartbeat === self::EVENT_DURATION + self::WAITING_TIME) {
                        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
                            $player->getInventory()->clearAll(true);
                            $player->sendTitle("§l§6Event Over");
                            $player->sendSubTitle("§eThank you for participating");
                        }
                    }
                    if (self::$heartbeat < $end_time) {
                        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
                            $player->sendPopup("§aTeleporting back to event lobby in §f" . ((self::EVENT_DURATION + self::WAITING_TIME + 15) - self::$heartbeat) . "s");
                        }
                    }
                    if (self::$heartbeat === $end_time) {
                        $level = $this->getServer()->getLevelByName("WaitingZone");
                        Utils::execAll(function ($player) use ($level) {
                            $player->teleport($level->getSpawnLocation());
                        });
                    }
                }
            }), 20);

    }

    public static function updateScoreboard(Player $player)
    {
        ScoreFactory::setScore($player, "§l§6Ownage §eEvent");
        foreach (self::getScoreboardLines($player) as $line_number => $line) {
            ScoreFactory::setScoreLine($player, $line_number + 1, $line);
        }
    }

    public static function getScoreboardLines(Player $player)
    {
        $t = explode(":", gmdate("i:s", self::WAITING_TIME - self::$heartbeat));
        $online = count(Server::getInstance()->getOnlinePlayers());
        $money = EconomyAPI::getInstance()->myMoney($player);
        $lines = [
            "§e︱- §6§lGeneral",
            "§e︱ §fOnline §7$online",
            "§e︱ §fMoney §7\$$money",
        ];
        if (self::$event_stage === -1) {
            $lines = array_merge($lines, [
                "§e︱- §6§lEvent",
                "§e︱ §fStarting In §a$t[0]m $t[1]s",
                "§e︱ §fRun §b/info§f to get ",
                "§e︱ §fmore information about event "
            ]);
        } elseif (self::$event_stage === 0 || self::$event_stage === 1) {
            $topmoney = self::$topmoney;
            $top = [];
            foreach ($topmoney as $username => $money) {
                $top[] = [$username, $money];
            }
            $t2 = explode(":", gmdate("i:s", (self::WAITING_TIME + self::EVENT_DURATION) - self::$heartbeat));
            $lines[] = self::$event_stage === 0 ? "§e︱ §fTime Remaining §a$t2[0]m $t2[1]s" : "§e︱ §cEvent Over";
            $lines = array_merge($lines, [
                "§e︱- §6§lLeaderboard",
                "§e︱ §l§e#1§r §a" . $top[0][0] . " §f-§b \$" . $top[0][1],
                "§e︱ §l§f#2§r §a" . $top[1][0] . " §f-§b \$" . $top[1][1],
                "§e︱ §l§6#3§r §a" . $top[2][0] . " §f-§b \$" . $top[2][1],
                "§e︱ §e§7#4 §a" . $top[3][0] . " §f-§b \$" . $top[3][1],
                "§e︱ §e§7#5 §a" . $top[4][0] . " §f-§b \$" . $top[4][1],
            ]);
        }
        return $lines;
    }


    public function generateIsland(Player $p)
    {
        $id = Utils::store_var($p);
        $this->getServer()->getAsyncPool()->submitTask(new AsyncIslandGeneratorTask($this->getDataFolder() . "/base_island", $p->getName(), function ($bool) use ($id) {
            $p = Utils::get_var($id);
            $server = Server::getInstance();
            if ($p instanceof Player) {
                if ($bool) {
                    $p->sendMessage(Base::prefix . "Island generated successfully");
                } else $p->sendMessage(Base::prefix . "Island failed to generate, contact Zed for more information");
                $server->loadLevel($p->getName());
                $p->teleport($server->getLevelByName($p->getName())->getSpawnLocation());
            }
        }));
    }

    public function sendIslandMenu(Player $p)
    {
        $has_island = file_exists("worlds/{$p->getName()}");
        $form = new SimpleForm(function (Player $player, $data = null) use ($has_island) {
            if ($data === null) {
                return;
            }
            if ($data === 0) {
                if ($has_island) {
                    $island = Server::getInstance()->getLevelByName($player->getName());
                    $player->teleport($island->getSpawnLocation());
                } else {
                    $this->generateIsland($player);
                }
            }
        });
        $form->setTitle("§8§l(§3ISLAND§8)");
        $form->setContent($has_island ? "Teleport to your island for the event\n\n\n" : "§fSelect create Island to create and teleport to your island for this event\n\n");
        $form->addButton($has_island ? "§9Teleport to your Island" : "§9Create Your Island");
        $p->sendForm($form);
    }

    public function infoUI(Player $p)
    {
        // skyblock op af
        $form = new SimpleForm(function (Player $player, $data = null) {

        });
        $form->setTitle("§l§4Event Information");
        $info_lines = [
            "",
            "§6§lObjective:§r",
            "§b- §fYou are to obtain as much money as possible, - farm, mine, sell crops etc",
            "§b- §f You will be able to quickly sell items by tapping the quick sell wand that will appear in far right hotbar slot",
            "§b- §f The event will last exactly §a1 hour §fafter which you will be teleported back to the event lobby",
            "",
            "§l§4Info:§r",
            "§b- §f You will start with §a$5000",
            "§b- §f There will be a chest on the island containing your kit",
            "§b- §f Tick Rate will be set to §a5x",
            "§b- §f Event time §a1hr",
            "§b- §f You cannot pay other users, or visit their island for the duration of the event"
        ];
        $form->setContent(implode("\n", $info_lines));
        $form->addButton("§0Ok");
        $p->sendForm($form);
    }


    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        switch ($command->getName()) {
            case "is":
                if (!$sender instanceof Player) {
                    return true;
                }

                if (isset($args[0]) && $sender->isOp()) {
                    if ($args[0] === "tickrate") {
                        Crops::$tickrate = (int)$args[1];
                    } elseif ($args[0] === "copy") {
                        $this->getServer()->getAsyncPool()->submitTask(new AsyncIslandGeneratorTask($this->getDataFolder() . "/base_island", $args[1], function ($bool) {
                            var_dump($bool);
                        }));
                    } elseif ($args[0] === "generate") {
                        $this->generateIsland($sender);
                    }elseif($args[0] === "heartbeat"){
                        self::$heartbeat = (int)$args[1];
                        $sender->sendMessage("Set heartbeat to $args[1]");
                    }elseif($args[0] === "stage"){
                        self::$event_stage = (int)$args[1];
                        $sender->sendMessage("Set stage to $args[1]");
                    }
                } else {
                    if(Base::$event_stage === 0) {
                        $this->sendIslandMenu($sender);
                    }else $sender->sendMessage(Base::prefix."You cannot use this command at the current event stage");
                }

                return true;
            case "info":
                if($sender instanceof Player) {
                    $this->infoUI($sender);
                }
                break;
            default:
                return false;
        }
    }

    public function onDisable(): void
    {
        $this->getLogger()->info("Bye");
        if (self::$event_stage === 0) {
            file_put_contents($this->getDataFolder() . "cache.json", ["heartbeat" => self::$heartbeat]);
        }
    }
}
