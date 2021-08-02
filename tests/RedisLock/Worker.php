<?php

namespace Workerfy\Tests\RedisLock;

use Common\Library\Lock\PHPRedisMutex;
use Common\Library\Lock\PredisMutex;
use malkusch\lock\exception\TimeoutException;

class Worker extends \Workerfy\AbstractProcess {

    public function run()
    {
        $predis = \Workerfy\Tests\Make::makePredis();
        if($this->getProcessWorkerId() == 1) {
            //sleep(1);
            var_dump('start-'.$this->getProcessWorkerId());
        }else {
            var_dump('start-0');
        }

        while(1)
        {
            $orderId = '12345';
            // lockKey与业务数据结合
            $lockKey = 'test_lock_'.$orderId;
            $mutex = new PredisMutex([$predis], $lockKey,10);
            var_dump($this->getProcessWorkerId());

            // 获得锁并回调同步处理
            try {
                // 获得锁,并进行回调处理, 业务尽可能简单处理，在规定时间内完成
                $mutex->synchronized(function () {
                    go(function ()
                    {
                        var_dump('get lock worker_id='.$this->getProcessWorkerId());
                        if($this->getProcessWorkerId() == 1)
                        {
                            sleep(2);
                        }else {
                            sleep(8);
                        }
                    });
//                    var_dump($this->getProcessWorkerId());
//                    sleep(9);

                    // 最好用事务处理，可以使用协程
    //                if($this->getProcessWorkerId() == 0) {
    //                    sleep(6);
    //                    var_dump('worker-id='.$this->getProcessWorkerId());
    //                }
    //                var_dump('test-lock-'.$this->getProcessWorkerId());
                });

            }catch (\Exception $exception) {
                // 超时还没获的锁
                if($exception instanceof TimeoutException)
                {
                    if($this->getProcessWorkerId() == 1)
                    {
                        var_dump($exception->getTraceAsString());
                    }
                }
            }
        }


    }

    public function onShutdown() {
        var_dump("shutdown-cid".\Co::getCid());
    }

    public function onReceive($str, ...$args)
    {

    }
}