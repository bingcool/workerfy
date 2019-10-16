<?php
namespace Workerfy\Tests\Dynamic;

class Monitor_Worker extends \Workerfy\AbstractProcess {

    public function run() {
            $start_time = time();
            while (1) {
                // 模拟处理业务
                sleep(1);

                // 模拟队列积压过多，发起动态创建的指令，通知父进程创建业务处理进程
                $dynamic_process_name = $this->getArgs()['monitor_process_name'];


                if(time() - $start_time > 2 && time() - $start_time < 4) {
                    //var_dump($dynamic_process_name);
                    $this->notifyMasterCreateDynamicProcess($dynamic_process_name, 2);
                }

                if(time() - $start_time > 5 && time() - $start_time < 8) {
                    $this->notifyMasterDestroyDynamicProcess($dynamic_process_name);
                }

                if(time() - $start_time > 25 && time() - $start_time < 30) {
                    var_dump('next');
                    $this->notifyMasterCreateDynamicProcess($dynamic_process_name, 2);
                }

//                $this->notifyMasterCreateDynamicProcess($dynamic_process_name, 2);
//
//                sleep(2);
//
//                $this->notifyMasterDestroyDynamicProcess($dynamic_process_name);


            }
    }
}