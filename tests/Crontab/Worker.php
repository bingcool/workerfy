<?php
namespace Workerfy\Tests\Crontab;

use Workerfy\Crontab\CrontabManager;
use Workerfy\ProcessManager;
use \Workerfy\Coroutine\Context;

class Worker extends \Workerfy\AbstractProcess {

    public $tick_format = "*/1 * * * *";

    public function init() {

        var_dump(base64_encode($this->tick_format));

        $tick_format = $this->getCliEnvParam('tick_format');
        var_dump($tick_format);

        if($tick_format) {
            $this->tick_format = $tick_format;
        }else {
            var_dump("cccccccccc");
            //throw new \Exception("cli command env params must be set tick_format", 1);
        }
    }

    public function run() {

        Context::set('name','bingcool');

        var_dump('worker_cid='.\Co::getCid());
        $this->tick_format = '*/1 * * * *';
        // 每分钟执行一次，时间格式类似于linux的crontab
        CrontabManager::getInstance()->addRule("tick", $this->tick_format , function($cron_name, $expression) {
            // 一定要在最外为捕捉异常，否则tick定时器里面的异常无法捕捉处理
            var_dump('tick_cid='.\Co::getCid());

            defer(function () {
                var_dump("defer defer");
            });

            try{

               // $timer_id = CrontabManager::getInstance()->getTimerIdByName('tick');

                //var_dump($timer_id);

                var_dump("一分钟时间到了,表达式=$expression,执行任务:".date('Y-m-d H:i:s', time()));

                if(date('Y-m-d H:i:s') == '2020-06-13 21:17:00') {
                    $this->reboot();
                    //CrontabManager::getInstance()->cancelCrontabTask($cron_name);
                }

            }catch (\Throwable $throwable) {
                $this->onHandleException($throwable);
            }
        }, 0);

        sleep(2);

        // 每分钟执行一次，时间格式类似于linux的crontab
        CrontabManager::getInstance()->addRule("tick1", '*/2 * * * *' , function($cron_name, $expression) {
            // 一定要在最外为捕捉异常，否则tick定时器里面的异常无法捕捉处理
            var_dump('tick_cid='.\Co::getCid());
            try{

                // $timer_id = CrontabManager::getInstance()->getTimerIdByName('tick');

                //var_dump($timer_id);

                var_dump("一分钟时间到了ddddddddd,表达式=$expression,执行任务:".date('Y-m-d H:i:s', time()));

                if(date('Y-m-d H:i:s') == '2020-06-13 00:13:00') {
                    CrontabManager::getInstance()->cancelCrontabTask($cron_name);
                }

            }catch (\Throwable $throwable) {
                $this->onHandleException($throwable);
            }
        }, 1);

        var_dump(Context::get('name'));

        Context::defer(function() {
            var_dump('hello');
            var_dump(Context::get('name'));
        });

        $channel = CrontabManager::getInstance()->getChannelByName('tick');
        var_dump($channel);

        //$timer_id = CrontabManager::getInstance()->getTimerIdByName('tick');
       // var_dump('创建了一个Crontable 每隔一分钟执行的任务，timer_id='.$timer_id);
    }

    public function onHandleException($t) {
        var_dump('Exception_cid='.\Co::getCid());
        var_dump($t->getMessage());
    }
}