<?php
namespace Workerfy\Tests\WhileTest;

class Worker extends \Workerfy\AbstractProcess {

    public function run() {
        while (1) {
            // 模拟处理业务
            \Co::sleep(1);
            $process_name = $this->getProcessName().'@'.$this->getProcessWorkerId();
            var_dump($process_name);
        }

    }

}