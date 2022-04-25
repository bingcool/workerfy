<?php
namespace Workerfy\Tests\Jobfy;

use Workerfy\ConfigLoader;
use Workerfy\Tests\Make;

class worker1 extends QueueProcess
{

    public function getQueueInstance()
    {
        return Make::makeRedis();
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
        $redis
    }


}