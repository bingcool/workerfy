<?php
namespace Workerfy\Tests\Queue;

use Workerfy\AbstractProcess;
use Workerfy\Tests\Make;

class QueueConsumerWorker extends AbstractProcess {

    protected $isPredisDriver = false;

    public function init(int $driver = 0)
    {
        if($driver <=0 )
        {
            $this->isPredisDriver = true;
        }
    }

    public function run() {
        // 模拟处理业务
        if($this->isPredisDriver)
        {
            $redis = Make::makePredis();
            var_dump("use Predis driver");
        }else
        {
            $redis = Make::makeRedis();
            var_dump('use Phpredis driver');
        }

        $queue = new \Common\Library\Queues\Queue(
            $redis,
            'ali_queue_key'
        );


        $queue->getRedis()->del(['ali_queue_key']);

        for($i=1; $i<=2; $i++)
        {
            $item = [
                'id' => $i,
                'name' => 'bingcool-'.$i
            ];

            $queue->push($item);
        }

        \Swoole\Timer::tick(3000,function () {
            var_dump('tick');
        });

        $queue->delRetryMessageKey();

        while(1)
        {
            try {
                $ret = $queue->pop($timeOut = 0);

                var_dump($ret, $queue->count());

                if($ret)
                {
                    $data = json_decode($ret[1], true) ?? [];

                    // 假设处理失败，然后放入失败重试队列，一定时间后再处理
                    if(isset($data['id']) && $data['id'] == 2)
                    {
                        $queue->retry($ret[1], 5);
                    }
                }

            }catch (\Exception $e)
            {
                $this->onHandleException($e);
            }
        }

    }

    public function onHandleException(\Throwable $throwable, array $context = [])
    {
        var_dump(get_class($throwable), $throwable->getMessage(), $throwable->getCode());
    }

}