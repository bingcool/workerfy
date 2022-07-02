<?php

namespace Workerfy\Tests\Gotimer;

use smtp\Exception;
use function foo\func;

class Worker extends \Workerfy\AbstractProcess
{
    /**
     * @inheritDoc
     */
    public function run()
    {
        $endTime = time() + 10;
        \Workerfy\Coroutine\Timer::tick(2000, function($timeChannel) use($endTime) {
            if(time() > $endTime) {
                // 取消当前的定时器
                \Workerfy\Coroutine\Timer::cancel($timeChannel);
            }
            var_dump("tick-".date('Y-m-d H:i:s'));
        });


        \Workerfy\Coroutine\Timer::after(5000, function() {
            var_dump("after-".date('Y-m-d H:i:s'));
        });

    }

    public function onHandleException(\Throwable $throwable, array $context = [])
    {
        var_dump($throwable->getMessage(), $throwable->getTraceAsString());
    }
}

