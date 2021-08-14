<?php
namespace Workerfy\Tests\Context;

class Worker extends \Workerfy\AbstractProcess {


    public $callNum = 0;

    public $startTime;
    public $runTime = 20;


    public function init() {
        var_dump('ggggggggggggggggggggggg');
        $this->startTime = time();
    }


    public function run() {

        defer(function () {
            $cid = \Co::getCid();
            var_dump($cid);
        });

        while (1) {

            if($this->isRebooting() == true) {
                return ;
            }

            if(time() - $this->startTime > $this->runTime) {
                $this->reboot(5);
            }

            // 模拟处理业务
            \Co::sleep(1);
            $process_name = $this->getProcessName().'@'.$this->getProcessWorkerId();
            var_dump($process_name);

            $db = \Workerfy\Tests\Make::makeMysql();
            $res = $db->query("select sleep(1)");
            var_dump($res);
            usleep(10000);

        }

    }

}