<?php
namespace Workerfy\Tests\Exits;

class Worker extends \Workerfy\AbstractProcess {

    public function run() {

        // 模拟处理业务
        var_dump("2s后子进程开始自动退出");
        sleep(2);
        //$this->exit(); //可以观察到子进程最终销毁掉
        $this->test();

    }

    public function onShutDown()
    {
        var_dump("shutdown");
        throw new \Exception("test exceptions");
    }

    public function test() {
        $this->test1();
    }

    public function test1() {
        throw new \Exception("test exceptions");
    }

    public function onHandleException($throwable)
    {
        $msg = $throwable->getMessage();
        var_dump($msg);
    }
}