<?php
namespace Workerfy\Tests\CliPipe;

class Worker extends \Workerfy\AbstractProcess {

    public function run() {
//        go(function () {
//            sleep(10);
//        });
//
//        go(function () {
//            sleep(10);
//        });
        // 模拟处理业务
        var_dump("start workerId:".$this->getProcessWorkerId());
        sleep(1);
        //var_dump("子进程 开始 reboot start");
        if($this->getProcessWorkerId() == 0) {
            //$this->reboot(); //可以观察到子进程pid在变化
            while (1) {
                $str = str_repeat("bing",1000);
                $used_memory = memory_get_usage();
                var_dump('worker0:'.$used_memory);
                sleep(2);
            }
        }

        if($this->getProcessWorkerId() == 1) {
            sleep(2);
            //$this->reboot(); //可以观察到子进程pid在变化
            while (1) {
                $used_memory = memory_get_usage();
                var_dump('worker1:'.$used_memory);
                sleep(2);
            }
        }

        if($this->getProcessWorkerId() == 2) {
            // 自身可以发起创建动态进程
            try {
                $this->notifyMasterCreateDynamicProcess($this->getProcessName(), 1);
            } catch (\Exception $e) {
            }
        }


    }

    public function onShutDown()
    {
        parent::onShutDown(); // TODO: Change the autogenerated stub
        //var_dump("shutdown--");
    }

//    public function __destruct()
//    {
//        var_dump("destruct");
//    }
}