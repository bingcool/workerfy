<?php
namespace Workerfy\Tests\Jobfy;

use Workerfy\Tests\Make;

class worker2 extends QueueProcess
{
    public function getQueueInstance()
    {
        $this->redis = Make::makeRedis();
    }

    /**
     * @inheritDoc
     */
    public function run()
    {
        var_dump(__CLASS__);
    }

    public function receive()
    {
        // TODO: Implement receive() method.
    }
}