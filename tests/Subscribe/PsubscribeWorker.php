<?php

namespace Workerfy\Tests\Subscribe;

use Workerfy\Crontab\CrontabManager;

class PsubscribeWorker extends \Workerfy\AbstractProcess {

    protected $isPredisDriver = 0;

    public function init($driver = 0)
    {
        $this->isPredisDriver = $driver;
    }

    public function run()
    {
        if($this->isPredisDriver) {
            $redis = new \Common\Library\Cache\Predis([
                'scheme' => 'tcp',
                'host'   => '127.0.0.1',
                'port'   => 6379,
                'read_write_timeout' => -1
            ]);
            $pubSub = new \Common\Library\PubSub\PredisPubSub($redis);
            var_dump( 'use Predis driver');
        }else {

            $redis = new \Common\Library\Cache\Redis();
            $redis->connect('127.0.0.1');
            $pubSub = new \Common\Library\PubSub\RedisPubSub($redis);

            var_dump('use phpredis driver');
        }

        while (true)
        {
            try {
                $pubSub->psubscribe(['test1*'], function($redis, $chan, $msg) {
                    //var_dump($this->getPid()."-receipe time =".date('Y-m-d H:i:s'));
                    var_dump($msg);
                });
            }catch (\Exception $e)
            {
                var_dump('exception='.$e->getMessage());
            }
        }


    }

    public function onShutdown() {
        var_dump("shutdown-cid".\Co::getCid());
    }
}