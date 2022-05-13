<?php
namespace Workerfy\Tests\Jobfy;

use Workerfy\ConfigLoader;
use Common\Library\Cache\Redis;
use Common\Library\Queues\RedisDelayQueue as DelayQueue;

abstract class RedisDelayQueue extends QueueProcess
{
    /**
     * @var DelayQueue $queue
     */
    protected $queue;

    /**
     * @return DelayQueue
     */
    public function getQueueInstance()
    {
        $config = ConfigLoader::getInstance()->getConfig()[$this->driver];
        $redis = new Redis();
        $redis->connect(
            $config['host'],
            $config['port'],
            $config['timeout'] ?? 2.0,
            $config['reserved'] ?? null,
            $config['retry_interval'] ?? 0,
            $config['read_timeout'] ?? 0.0
        );

        return new DelayQueue(
            $redis,
            $this->queueName
        );
    }

    /**
     * @inheritDoc
     */
    public function run()
    {
        while(true)
        {
            try {
                if(!$this->checkCanContinueHandle())
                {
                    continue;
                }

                $result = $this->queue->rangeByScore('-inf', time(),  ['limit' =>[0, 2]]);
                $this->handleNum++;

                foreach($result as $item) {
                    try {
                        $this->handle($item);
                    }catch (\Throwable $exception) {
                        $this->onHandleException($exception, $item ?? []);
                    }
                }

            }catch (\Throwable $exception) {
                $this->onHandleException($exception, $result ?? []);
            }
            sleep(1);
        }
    }
}