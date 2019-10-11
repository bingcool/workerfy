<?php
namespace Workerfy\Tests\Exits;

class Worker extends \Workerfy\AbstractProcess {

    public function run() {

        // 模拟处理业务
        sleep(10);

        $this->exit(); //可以观察到子进程最终销毁掉

    }
}