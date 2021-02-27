<?php
namespace Workerfy\Tests\Reboot;

class Worker extends \Workerfy\AbstractProcess {

    protected $sleep;

    // 外部cli方式传入--sleep=5
    public function init($sleep = 3)
    {
        var_dump($sleep);
        $this->sleep = $sleep;
    }

    public function run() {

        while (true)
        {
            if(time() - $this->getStartTime() > $this->sleep)
            {
                var_dump("子进程开始 reboot start");
                $this->reboot();
            }

            // 模拟处理业务
            sleep(1);

        }

    }

    public function onShutDown()
    {
        parent::onShutDown(); // TODO: Change the autogenerated stub
        var_dump("子进程 shutdown--".\Co::getCid());
    }

//    public function __destruct()
//    {
//        var_dump("destruct");
//    }
}