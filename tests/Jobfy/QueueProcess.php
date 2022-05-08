<?php
namespace Workerfy\Tests\Jobfy;

use Workerfy\AbstractProcess;

abstract class QueueProcess extends AbstractProcess
{
    /**
     * 队列前缀
     */
    const PREFIX_KEY = 'workerfy:queue:';

    /**
     * @var string 队列名称
     */
    protected $queueName;

    /**
     * @var int 默认消费达到10000后reboot进程
     */
    protected $maxHandle = 10000;

    /**
     * @var int
     */
    protected $lifeTime = 3600;

    /**
     * @var int 队列积压数
     */
    protected $dynamicQueueCreateBacklog = 500;

    /**
     * @var int 队列积压数
     */
    protected $dynamicQueueDestroyBacklog = 200;

    /**
     * @var int 动态创建的进程数据
     */
    protected $dynamicQueueWorkerNum = 4;

    /**
     * 重试次数
     *
     * @var int
     */
    protected $retryNum = 2;

    /**
     * 延迟5s后放回主队列重试
     *
     * @var int
     */
    protected $retryDelayTime = 5;

    /**
     * 超过多少秒没有被消费，就抛弃，0代表永不抛弃
     *
     * @var int
     */
    protected $ttl = 0;

    /**
     * @var int
     */
    protected $destroyPreTime = 0;

    /**
     * @var int
     */
    protected $handleNum = 0;

    /**
     * @var string
     */
    protected $driver = 'redis-queue';

    /**
     * init
     */
    public function init()
    {
        $this->queueName = static::PREFIX_KEY.$this->getArgs()['alias_queue_name'];
        $this->maxHandle = $this->getArgs()['max_handle'] ?? $this->maxHandle;
        $this->lifeTime  = $this->getArgs()['life_time'] ?? $this->lifeTime;
        $this->dynamicQueueCreateBacklog = $this->getArgs()['dynamic_queue_create_backlog'] ?? $this->dynamicQueueCreateBacklog;
        $this->dynamicQueueDestroyBacklog = $this->getArgs()['dynamic_queue_destroy_backlog'] ?? $this->dynamicQueueDestroyBacklog;
        $this->dynamicQueueWorkerNum = $this->getArgs()['dynamic_queue_worker_num'] ?? $this->dynamicQueueWorkerNum;
        $this->retryNum = $this->getArgs()['retry_num'] ?? $this->retryNum;
        $this->retryDelayTime = $this->getArgs()['retry_delay_time'] ?? $this->retryDelayTime;
        $this->ttl = $this->getArgs()['ttl'] ?? $this->ttl;
        $this->driver = $this->getArgs()['driver'] ?? $this->driver;
        $this->queue = $this->getQueueInstance();
        $this->monitorQueue();
        $this->registerTickReboot($this->lifeTime);
    }

    /**
     * monitorQueue
     */
    protected function monitorQueue()
    {
        if($this->getProcessWorkerId() == 0) {
            \Swoole\Timer::tick(5000, function () {
                $queue = $this->getQueueInstance();
                if($this instanceof RedisQueue) {
                    $queueBacklog = $queue->count();
                }else if($this instanceof RedisDelayQueue) {
                    $queueBacklog = $queue->count('-inf', time() + 1);
                }

                if(isset($queueBacklog)) {
                    if($queueBacklog > $this->dynamicQueueCreateBacklog) {
                        $this->notifyMasterCreateDynamicProcess($this->getProcessName(), $this->dynamicQueueWorkerNum);
                    }
                    if($queueBacklog < $this->dynamicQueueDestroyBacklog && (time() - $this->destroyPreTime) > 300) {
                        $this->destroyPreTime = time();
                        $this->notifyMasterDestroyDynamicProcess($this->getProcessName());
                    }
                }
                unset($queue);
            });
        }
    }

    /**
     * @return mixed
     */
    abstract public function getQueueInstance();

    /**
     * @param array $data
     * @return mixed
     */
    abstract public function handle(array $data);
}