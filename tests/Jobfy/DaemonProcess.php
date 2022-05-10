<?php
namespace Workerfy\Tests\Jobfy;

use Workerfy\AbstractProcess;

abstract class DaemonProcess extends AbstractProcess
{

    /**
     * @var int
     */
    protected $maxHandle = 10000;

    /**
     * @var int
     */
    protected $lifeTime = 3600;

    /**
     * init
     */
    protected function init()
    {
        $this->maxHandle = $this->getArgs()['max_handle'] ?? $this->maxHandle;
        $this->lifeTime  = $this->getArgs()['life_time'] ?? $this->lifeTime;
        $this->onInit();
    }

    /**
     * onInit
     */
    protected function onInit()
    {

    }

    /**
     * afterReboot
     */
    protected function onAfterReboot()
    {

    }

    /**
     * CreateDynamicProcess
     *
     * @param $dynamic_process_name
     * @param $dynamic_process_num
     */
    protected function onCreateDynamicProcessCallback($dynamic_process_name, $dynamic_process_num)
    {

    }

    /**
     * DestroyDynamicProcess
     *
     * @param $dynamic_process_name
     * @param $dynamic_process_num
     */
    protected function onDestroyDynamicProcessCallback($dynamic_process_name, $dynamic_process_num)
    {

    }

    /**
     * onShutDown
     */
    public function onShutDown()
    {
        parent::onShutDown();
    }

}