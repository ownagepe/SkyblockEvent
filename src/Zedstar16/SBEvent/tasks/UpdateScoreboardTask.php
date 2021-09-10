<?php

namespace Zedstar16\SBEvent\tasks;

use pocketmine\scheduler\Task;
use pocketmine\Server;
use Zedstar16\SBEvent\Base;

class UpdateScoreboardTask extends Task
{

    public $queue = [];

    public $batch_size = 0;

    public function __construct()
    {
        $this->queue = Server::getInstance()->getOnlinePlayers();
        $this->batch_size = ceil(count($this->queue)/20);
    }

    public function onRun(int $currentTick)
    {
        $i = 0;
        if(empty($this->queue)){
            $this->getHandler()->cancel();
            return;
        }
        foreach ($this->queue as $key => $player){
            if($this->batch_size < $i){
                break;
            }
            Base::updateScoreboard($player);
            unset($this->queue[$key]);
            $i++;
        }
        if($currentTick % 20 === 0){
            if(!empty($this->queue)){
                foreach ($this->queue as $key => $player){
                    Base::updateScoreboard($player);
                }
                $this->queue = [];
            }
            $this->getHandler()->cancel();
        }
    }
}