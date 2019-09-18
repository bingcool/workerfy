<?php
namespace Workerfy\Tests\Crontab;

use Workerfy\Crontab\CrontabManager;
use Workerfy\ProcessManager;

class Timer extends \Workerfy\AbstractProcess {

    public function run() {
        CrontabManager::getInstance()->addRule("tick", "* * * * *" , function() {
            //var_dump("this is a tick test");
            //$this->time();
        });

        $timer_id = CrontabManager::getInstance()->getTimerIdByName('tick');

        var_dump($timer_id);

        $test = new \stdClass();
        $test->test = 1;
        swoole_timer_tick(1000, function () use($test) {
            if($test->test) {
                $test->test = 0;
                sleep(5);
                var_dump("sleep");
            }else {
                var_dump("no sleep");

            }
        });
    }

    public function time() {
        var_dump('Cid-'.\Co::getCid());
        var_dump($this->getProcessName().'-'.rand(1,100));
        if(\Co::getCid() > 80) {
            var_dump(\Co::getCid());
            $this->reboot();
        }
    }
}