<?php
namespace Workerfy\Tests\Dynamic;

class Monitor_Worker extends \Workerfy\AbstractProcess {

    public function run() {
            while (1) {
                // 模拟处理业务
                sleep(1);

                // 模拟队列积压过多，发起动态创建的指令，通知父进程创建业务处理进程
                $dynamic_process_name = $this->getArgs()['monitor_process_name'];
                //var_dump($dynamic_process_name);
                $this->notifyMasterCreateDynamicProcess($dynamic_process_name, 2);

                sleep(2);

                $this->notifyMasterDestroyDynamicProcess($dynamic_process_name);

//                // 20s后，队列积压减少，那么通知父进程销毁动态创建进程
//                sleep(5);
//
//

//                sleep(5);
//
//                $this->notifyMasterCreateDynamicProcess($dynamic_process_name, 2);



            }




    }
}