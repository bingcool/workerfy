<?php
namespace Workerfy\Tests\Table;

class Worker extends \Workerfy\AbstractProcess {

    public function init() {
        defer(function() {
            //var_dump("coroutine_destruct");
        });
    }

    public function run() {
        // 模拟处理业务
        if($this->getProcessWorkerId() == 1) {
            // sleep 4秒后，重新读取到worker0设置的新值
            sleep(2);
            $table = \Workerfy\Memory\TableManager::getInstance()->getTable('redis-table');
            $value = $table->get('redis_test_data','tick_tasks');
            var_dump($this->getProcessName().'@'.$this->getProcessWorkerId().' : '.$value);
        }else {
            // 读取父进程设置的值
            $table = \Workerfy\Memory\TableManager::getInstance()->getTable('redis-table');
            $value = $table->get('redis_test_data','tick_tasks');
            var_dump($this->getProcessName().'@'.$this->getProcessWorkerId().' : '.$value);

            // 重新更该值
            $table->set('redis_test_data', [
                'tick_tasks'=>'hello, 我是worker@0 ,我重新设置的新值'
            ]);

            // 通知父进程读，看是否可以读到新值
            $this->writeToMasterProcess(\Workerfy\ProcessManager::MASTER_WORKER_NAME,['hello']);

        }
    }
}