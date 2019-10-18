<?php
namespace Workerfy\Tests\Crontab;

use Workerfy\Crontab\CrontabManager;
use Workerfy\ProcessManager;

class Worker extends \Workerfy\AbstractProcess {

    public function run() {
        // 每分钟执行一次，时间格式类似于linux的crontab
        CrontabManager::getInstance()->addRule("tick", "* * * * *" , function() {
            var_dump('一分钟时间到了，执行任务:'.date('Y-m-d H:i:s', time()));
        });

        $timer_id = CrontabManager::getInstance()->getTimerIdByName('tick');

        var_dump('创建了一个Crontable 每隔一分钟执行的任务，timer_id='.$timer_id);
    }

    public function time() {

    }
}