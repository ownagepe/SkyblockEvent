<?php


namespace Zedstar16\SBEvent;


use Zedstar16\SBEvent\tasks\PlayerExecTask;

class Utils
{

    private static $store = [];

    public static function execAll(callable $callback, $tick = 1){
        Base::$instance->getScheduler()->scheduleRepeatingTask(new PlayerExecTask($callback), $tick);
    }

    public static function store_var($var)
    {
        $id = mt_rand(0, 123456789);
        self::$store[$id] = $var;
        return $id;
    }

    public static function get_var($id)
    {
        $var = self::$store[$id];
        unset(self::$store[$id]);
        return $var;
    }

    public static function recursive_delete($dir){

    }


}