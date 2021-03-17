<?php
namespace Workerfy\Tests\RegisterTimer;

class Worker extends \Workerfy\AbstractProcess {

    // 外部cli方式传入--sleep=5
    public function init($sleep = 3)
    {
        // 每120s重启一次
        //$this->registerTickReboot(120);
        // 每分钟重启一次，或者在半夜适当时候重启
        $this->registerTickReboot('*/1 * * * *');
    }

    public function run() {
        while (1)
        {
            var_dump('hello-'.rand(1,10000));
            sleep(10);
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