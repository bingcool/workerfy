<?php

namespace Workerfy\Tests\Subscribe;

class Worker extends \Workerfy\AbstractProcess {

    public function run()
    {
        $redis = \Workerfy\Tests\Redis::getMasterRedis();

        var_dump('start start start');

        $redis->subscribe(['sys_collector_channel'], function($redis, $chan, $msg) {
            var_dump($this->getPid()."-start=".date('Y-m-d H:i:s'));
            // 必须使用协程，防止逻辑业务阻塞，会造成阻塞订阅回调处理，丢失数据，无法处理
            go(function () use($chan, $msg) {
                try {
                    var_dump($chan);
                }catch (\Exception $e) {
                    var_dump($e->getMessage());
                }
            });

            // 协程达到一定数量后重启
            if($this->getCurrentCoroutineLastCid() > 10) {
                $this->reboot();
            }

        });

    }

    public function onShutdown() {
        var_dump("shutdown-cid".\Co::getCid());
    }

    public function onReceive($str, ...$args)
    {

    }
}