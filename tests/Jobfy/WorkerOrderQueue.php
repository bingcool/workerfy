<?php
namespace Workerfy\Tests\Jobfy;

class WorkerOrderQueue extends RedisQueue
{

    public function onAfterReboot()
    {
        var_dump(__FUNCTION__);
    }

    /**
     * @inheritDoc
     */
    public function handle(array $data)
    {
        // 超时不处理
        if(isset($data['__push_time']) && (time() - $data['__push_time']) > $this->ttl) {
           return;
        }

        // 超过重试次数不处理
        if(isset($data['__retry_count']) && $data['__retry_count'] > $this->retryNum) {
            return;
        }

        // 假设处理失败，然后放入失败重试队列，一定时间后再处理
//        if(isset($data['id']) && $data['id'] == 2)
//        {
//            $data['__push_time'] = time();
//            $data['__retry_count']++;
//            $this->queue->retry($data, 5);
//        }
    }

    public function onHandleException(\Throwable $throwable, array $context = [])
    {
        var_dump($throwable->getMessage(), $throwable->getTraceAsString());
    }
}