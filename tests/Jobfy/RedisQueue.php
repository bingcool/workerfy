<?php
namespace Workerfy\Tests\Jobfy;

use Workerfy\ConfigLoader;
use Common\Library\Cache\Redis;
use Common\Library\Queues\Queue;

abstract class RedisQueue extends QueueProcess
{
    /**
     * @var Queue $queue
     */
    protected $queue;

    /**
     * @return Queue
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

        return new Queue(
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
                if($this->isExiting() || $this->isRebooting()) {
                    sleep(1);
                    continue;
                }

                if($this->isStaticProcess() && $this->handleNum > $this->maxHandle) {
                    $this->reboot(2);
                    continue;
                }

                $result = $this->queue->pop($timeOut = 0);
                $this->handleNum++;

                if($result)
                {
                    $data = json_decode($result[1], true) ?? [];
                    $this->handle($data);
                }
            }catch (\Throwable $exception) {
                $this->onHandleException($exception, $data ?? []);
            }
        }
    }
}