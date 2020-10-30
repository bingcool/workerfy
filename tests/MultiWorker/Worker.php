<?php
namespace Workerfy\Tests\MultiWorker;

use PDO;

class Worker extends \Workerfy\AbstractProcess {

    public function init() {}

    public function run() {
        // 模拟处理业务，按照user_id取模分发到不同的其他worker处理
        sleep(1);
        var_dump('worker'.$this->getProcessWorkerId());
    }

    public function onShutDown()
    {
        parent::onShutDown(); // TODO: Change the autogenerated stub
        var_dump("shutdown--");
    }

//    public function __destruct()
//    {
//        var_dump("destruct");
//    }
}