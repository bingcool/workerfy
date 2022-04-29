<?php
namespace Workerfy\Tests\Jobfy;

use Workerfy\ConfigLoader;
use Common\Library\Cache\Redis;

class RedisQueue extends QueueProcess
{

    /**
     * @var Redis
     */
    protected $redis;

    /**
     * @return Redis
     */
    public function getQueueInstance()
    {
        $config = ConfigLoader::getInstance()->getConfig()[$this->driver];
        $this->redis = new Redis();
        $this->redis->connect($config['host'], $config['port'], $config['timeout']);
        return $this->redis;
    }

    /**
     * @inheritDoc
     */
    public function doHandle(array $data)
    {
        $config = ConfigLoader::getInstance()->getConfig();
        //var_dump($config);
    }

    public function receive()
    {
        $redis = $this->getQueueInstance();
    }


}