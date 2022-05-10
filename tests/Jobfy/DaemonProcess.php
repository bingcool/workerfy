<?php
namespace Workerfy\Tests\Jobfy;

use Workerfy\AbstractProcess;

abstract class DaemonProcess extends AbstractProcess
{

    /**
     * @var int 默认消费达到10000后reboot进程
     */
    protected $maxHandle = 10000;

    /**
     * @var int
     */
    protected $lifeTime = 3600;

    /**
     * init
     */
    public function init()
    {
        $this->maxHandle = $this->getArgs()['max_handle'] ?? $this->maxHandle;
        $this->lifeTime  = $this->getArgs()['life_time'] ?? $this->lifeTime;
    }
}