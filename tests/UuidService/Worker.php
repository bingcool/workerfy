<?php
namespace Workerfy\Tests\UuidService;

class Worker extends \Workerfy\AbstractProcess {

    protected $isPredisDriver = false;

    public function init(int $driver = 0)
    {
        if($driver <=0 )
        {
            $this->isPredisDriver = true;
        }

    }

    public function run() {
        $server = new \Co\Http\Server("*", 9502, false, true);
        $total = 0;

        swoole_timer_after(20*1000,function () use(&$total) {
            $logger = \Workerfy\Log\LogManager::getInstance()->getLogger(\Workerfy\Log\LogManager::RUNTIME_ERROR_TYPE);
            $logger->info('total='.$total);

        });

        $server->handle('/generaUuid', function ($request, $response) use(&$total) {
            try {
                // 模拟处理业务
                if($this->isPredisDriver)
                {
                    $redis = new \Common\Library\Cache\Predis([
                        'scheme' => 'tcp',
                        'host'   => '127.0.0.1',
                        'port'   => 6379,
                    ]);
                    //var_dump("use Predis driver");
                }else
                {
                    $redis = new \Common\Library\Cache\Redis();
                    $redis->connect('127.0.0.1');
                    //var_dump('use Phpredis driver');
                }

                $redisIncrement = new \Common\Library\Uuid\RedisIncrement($redis,'order_incr_id');
                $uuid = $redisIncrement->getIncrId();

                ++$total;

                //sleep(5);
                /**
                 * @var \Swoole\Http\Response $response
                 */
                $response->end(json_encode([
                    'id' => $uuid
                ]));

            }catch (\Throwable $e)
            {
                $this->onHandleException($e);
            }

        });

        $server->start();
    }

    public function onShutDown()
    {
        parent::onShutDown(); // TODO: Change the autogenerated stub

    }

    public function onHandleException(\Throwable $throwable)
    {
        parent::onHandleException($throwable); // TODO: Change the autogenerated stub
        var_dump($throwable->getMessage());
    }
}