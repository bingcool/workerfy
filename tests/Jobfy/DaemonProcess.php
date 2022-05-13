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
     * @var int
     */
    protected $currentRunCoroutineLastCid = 50000;

    /**
     * @var null
     */
    protected $limitCurrentRunCoroutineNum = null;

    /**
     * init
     */
    protected function init()
    {
        $this->maxHandle = $this->getArgs()['max_handle'] ?? $this->maxHandle;
        $this->lifeTime  = $this->getArgs()['life_time'] ?? $this->lifeTime;
        $this->currentRunCoroutineLastCid = $this->getArgs()['current_run_coroutine_last_cid'] ?? $this->maxHandle * 10;
        $this->limitCurrentRunCoroutineNum = $this->getArgs()['limit_run_coroutine_num'] ?? null;
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