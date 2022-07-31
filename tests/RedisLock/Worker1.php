<?php

namespace Workerfy\Tests\RedisLock;

use Common\Library\Lock\PHPRedisMutex;
use Common\Library\Lock\PredisMutex;
use malkusch\lock\exception\TimeoutException;

class Worker1 extends \Workerfy\AbstractProcess {

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
            //var_dump($this->getProcessWorkerId());

            // 获取锁
            $lock = $mutex->acquireLock();
            //var_dump($lock);

            try {
                // 处理业务
                if($lock)
                {
                    //var_dump('get lock worker_id='.$this->getProcessWorkerId());
                    // 模拟处理业务
                    sleep(2);
                    $mutex->releaseLock();
                }else
                {
                    // 同一时间没获得锁的
                    // 放在延迟队列，或者不确认是否完全消费
                }
            }catch (\Throwable $throwable)
            {
                $mutex->releaseLock();
            }

            \Swoole\Coroutine\System::sleep(0.01);
        }


    }

    public function onShutdown() {
        var_dump("shutdown-cid".\Co::getCid());
    }

    public function onReceive($str, ...$args)
    {

    }
}