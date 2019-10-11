<?php
namespace Workerfy\Tests\Dynamic;

class Monitor_Worker extends \Workerfy\AbstractProcess {

    public function run() {

        \Swoole\Timer::tick(1000, function () {
            // 模拟处理业务
            sleep(5);

            // 模拟队列积压过多，发起动态创建的指令，通知父进程创建业务处理进程

            $this->notifyMasterCreateDynamicProcess();

            // 20s后，队列积压减少，那么通知父进程销毁动态创建进程
            sleep(20);

            $this->notifyMasterDestroyDynamicProcess();

        });

    }
}