<?php


namespace EasySwoole\EasySwoole;


use EasySwoole\AtomicLimit\AtomicLimit;
use EasySwoole\Component\Di;
use EasySwoole\EasySwoole\AbstractInterface\Event;
use EasySwoole\EasySwoole\Swoole\EventRegister;

class EasySwooleEvent implements Event
{
    public static function initialize()
    {
        date_default_timezone_set('Asia/Shanghai');
    }

    public static function mainServerCreate(EventRegister $register)
    {
        // $limit  =   new AtomicLimit();
        // $limit->setLimitQps(1);
        // $limit->attachServer(ServerManager::getInstance()->getSwooleServer());
        // Di::getInstance()->set('limiter',$limit);
    }
}