<?php
namespace Workerfy\Tests\Pool;

use Swoole\Coroutine\Channel;
use Workerfy\Tests\Make;

class RedisPoolWorker extends \Workerfy\AbstractProcess {

    /**
     * run 进程创建后的run方法
     * @return void
     */
    public function run()
    {
        $pool = new \Common\Library\Pool\MysqlPool(function () {
            return Make::makePredis();
        });

        while (1)
        {
            go(function () use($pool) {
                try {
                    $redis = $pool->get();
                    var_dump(spl_object_id($redis));
                    sleep(1);
                }catch (\Throwable $e)
                {
                    $this->onHandleException($e);
                } finally {
                    $pool->put($redis);
                }
            });

            go(function () use($pool) {
                try {
                    $redis = $pool->get();
                    var_dump(spl_object_id($redis));
                    sleep(1);
                }catch (\Throwable $e)
                {
                    $this->onHandleException($e);
                } finally {
                    $pool->put($redis);
                }
            });

            go(function () use($pool) {
                try {
                    $redis = $pool->get();
                    var_dump(spl_object_id($redis));
                    sleep(1);
                }catch (\Throwable $e)
                {
                    $this->onHandleException($e);
                } finally {
                    $pool->put($redis);
                }
            });

            sleep(1);

        }
    }

    public function onHandleException(\Throwable $throwable, array $context = [])
    {
        var_dump($throwable->getMessage());
    }
}