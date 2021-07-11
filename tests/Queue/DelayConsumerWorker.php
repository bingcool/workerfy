<?php
namespace Workerfy\Tests\Queue;

class DelayConsumerWorker extends \Workerfy\AbstractProcess {

    protected $isPredisDriver = false;

    public function init(int $driver = 0)
    {
        if($driver <=0 )
        {
            $this->isPredisDriver = true;
        }
    }

    /**
     * run 进程创建后的run方法
     * @return void
     */
    public function run()
    {
        if($this->isPredisDriver) {
            $redis = new \Common\Library\Cache\Predis([
                'scheme' => 'tcp',
                'host'   => '127.0.0.1',
                'port'   => 6379,
            ]);

            $queue = new \Common\Library\Queues\PredisDelayQueue(
                $redis,
                'ali_delay_key'
            );
            var_dump( 'use Predis driver');
        }else {

            $redis = new \Common\Library\Cache\Redis();
            $redis->connect('127.0.0.1');

            $queue = new \Common\Library\Queues\RedisDelayQueue(
                $redis,
                'ali_delay_key'
            );

            var_dump('use phpredis driver');
        }

        $member1 = json_encode(['lead_id'=>123,'name'=>'lead1']);
        $member2 = json_encode(['lead_id'=>124,'name'=>'lead2']);

        $queue->addItem(time(), 123, 5)
            ->addItem(time(), 124, 10)
            ->push();

        var_dump('延迟队列长度:'.$queue->count('-inf','+inf'));

        var_dump('延迟队列某个member自增:'.$queue->incrBy(2,124));


        $startTime = 0;

        $queue->getRedis()->del($queue->getRetryMessageKey());

        while (true)
        {
            try
            {
                sleep(1);

                $endTime = time();

                $result = $queue->rangeByScore('-inf', time(),  ['limit' =>[0,9]]);

                $startTime = $endTime - 10;

                foreach($result as $id)
                {
                    if($id == 123)
                    {
                        $queue->retry(123, 5);
                    }
                }

                var_dump($result);

            }catch (\Throwable $throwable)
            {
                $this->onHandleException($throwable);
            }

        }
    }

    public function onHandleException(\Throwable $throwable, array $context = [])
    {
        switch (get_class($throwable))
        {
            case 'RedisException':

                break;
        }
        var_dump($throwable->getMessage());
    }
}