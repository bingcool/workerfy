<?php
namespace Workerfy\Tests\Reboot;

class Worker extends \Workerfy\AbstractProcess {

    protected $sleep;

    // 外部cli方式传入--sleep=5
    public function init($sleep = 3)
    {
        $this->sleep = $sleep;
        var_dump(\Swoole\Coroutine::getOptions());

        \Swoole\Timer::tick(1*1000, function (){
           var_dump('tick-'.\Co::getCid().'-'.rand(1,1000));
        });
    }

    public function run() {

        while (true)
        {
            try
            {
                if(time() - $this->getStartTime() > $this->sleep)
                {
                    var_dump("子进程开始 reboot start");
                    $this->reboot();
                }
                // 模拟处理业务
                sleep(1);
            }catch (\Throwable $e)
            {
                $this->onHandleException($e);
            }
        }

    }

    // 有时需要上报一下reboot的信息，主要是发生异常的时候或者业务上主动reboot，可以上报，方便随时了解信息
    public function afterReboot()
    {
        var_dump('after reboot-count='.$this->getRebootCount());
    }

    public function onShutDown()
    {
        parent::onShutDown(); // TODO: Change the autogenerated stub
        var_dump("子进程 shutdown--".\Co::getCid());
    }

}