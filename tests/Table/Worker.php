<?php
namespace Workerfy\Tests\Table;

class Worker extends \Workerfy\AbstractProcess {

    public function init() {
        defer(function() {
            //var_dump("coroutine_destruct");
        });
    }

    public function run() {
        $process_name = '子进程'.$this->getProcessName().'@'.$this->getProcessWorkerId();
        // 模拟处理业务
        if($this->getProcessWorkerId() == 1) {
            // sleep 4秒后，重新读取到worker0设置的新值
            sleep(1);
            $table = \Workerfy\Memory\TableManager::getInstance()->getTable('redis-table');
            $value = $table->get('redis_test_data','tick_tasks');
            var_dump($process_name.'读取到table的值'.' : '.$value);
        }else {
            // 读取父进程设置的值
            $table = \Workerfy\Memory\TableManager::getInstance()->getTable('redis-table');
            $value = $table->get('redis_test_data','tick_tasks');
            var_dump($process_name.'读取到table的值'.' : '.$value);

            // 重新更该值
            $value = "hello, 我是worker@0 , 我重新设置的新值";
            $table->set('redis_test_data', [
                'tick_tasks'=> $value
            ]);

            var_dump($process_name.'设置table的值：'.$value);

            // 读取配置
            $setting = \Workerfy\Memory\TableManager::getInstance()->getTableSetting('redis-table');
            var_dump($setting);

            // 通知父进程读，看是否可以读到新值
            $this->writeToMasterProcess(\Workerfy\ProcessManager::MASTER_WORKER_NAME,['hello']);

        }
    }
}