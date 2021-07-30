<?php

namespace Workerfy\Tests\RedisLock;

use Common\Library\Lock\PHPRedisMutex;
use Common\Library\Lock\PredisMutex;

class Worker extends \Workerfy\AbstractProcess {

    public function run()
    {
        $predis = \Workerfy\Tests\Make::makePredis();
        if($this->getProcessWorkerId() == 1) {
            sleep(1);
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
            try {
                // 获得锁,并进行回调处理, 业务尽可能简单处理，在规定时间内完成
                $mutex->synchronized(function () {
                    go(function ()
                    {
                        var_dump($this->getProcessWorkerId());
                        if($this->getProcessWorkerId() == 1)
                        {
                            sleep(2);
                        }else {
                            sleep(8);
                        }
                    });
//                    var_dump($this->getProcessWorkerId());
//                    sleep(9);

                    // 最好用事务处理，这里不能使用协程，因为使用协程，就会让出cpu控制权，然后就会就会直接执行release,释放锁。所以要阻塞执行
    //                if($this->getProcessWorkerId() == 0) {
    //                    sleep(6);
    //                    var_dump('worker-id='.$this->getProcessWorkerId());
    //                }
    //                var_dump('test-lock-'.$this->getProcessWorkerId());
                });

            }catch (\Exception $exception) {
                if($this->getProcessWorkerId() == 1)
                {
                    var_dump($exception->getMessage());
                }

            }
        }


    }

    public function getLuaScript() {
        return $script = <<<lua
        local name = KEYS[1];
        local sex = KEYS[2];
        
        local nameValue = ARGV[1];
        local sexValue = ARGV[2];
        
        redis.call('set',name, nameValue);
        redis.call('set',sex, sexValue);
        
        return 1;
lua;

    }

    public function onShutdown() {
        var_dump("shutdown-cid".\Co::getCid());
    }

    public function onReceive($str, ...$args)
    {

    }
}