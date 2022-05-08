<?php
namespace Workerfy\Tests\Jobfy;

use Common\Library\Cache\Redis;
use Common\Library\Queues\Queue;
use Workerfy\AbstractProcess;
use Workerfy\ConfigLoader;

class WorkerPushQueue extends AbstractProcess
{
    /**
     * 队列前缀
     */
    const PREFIX_KEY = 'workerfy:queue:';

    /**
     * @var Queue $queue
     */
    protected $queue;

    /**
     * @var string
     */
    protected $queueName;

    public function init()
    {
        $config = ConfigLoader::getInstance()->getConfig()['redis'];
        $redis = new Redis();
        $redis->connect(
            $config['host'],
            $config['port'],
            $config['timeout'] ?? 2.0,
            $config['reserved'] ?? null,
            $config['retry_interval'] ?? 0,
            $config['read_timeout'] ?? 0.0
        );

        $this->queueName = static::PREFIX_KEY.'worker-queue1';

        $this->queue = new Queue(
            $redis,
            $this->queueName
        );

        var_dump($this->queueName);
    }


    /**
     * @inheritDoc
     */
    public function run()
    {
        $this->queue->getRedis()->del([$this->queueName]);

        for($i=1; $i<=110; $i++)
        {
            $item = [
                'id' => $i,
                'name' => 'bingcool-'.$i
            ];

            $this->queue->push($item);
        }
    }

    public function onHandleException(\Throwable $throwable, array $context = [])
    {
        var_dump($throwable->getMessage(), $throwable->getTraceAsString());
    }
}