<?php
namespace Workerfy\Tests\CrontabBatch;

use Workerfy\Command\CommandRunner;
use Workerfy\Crontab\CrontabManager;
use Workerfy\Exception\CrontabException;
use Workerfy\ProcessManager;
use \Workerfy\Coroutine\Context;

/**
 * cronTask 批量处理任务类，可以只需要配置文件即可，不需要重启，可以大批量处理定时任务，调用php-fpm模式脚本
 * Class Worker
 * @package Workerfy\Tests\CrontabBatch
 */

class Worker extends \Workerfy\AbstractProcess
{
    /**
     * @var array
     */
    public $cronTasks = [];

    public function init() {
        // 定时读取最新配置文件
        $this->cronTasks = require(__DIR__ . './cron_task_conf.php');
        \Swoole\Timer::tick(5000, function () {
            $this->cronTasks = require(__DIR__ . './cron_task_conf.php');
            //var_dump($this->cronTasks);
        });

    }

    /**
     * @inheritDoc
     */
    public function run()
    {
        foreach ($this->cronTasks as $taskName => $item) {
            CrontabManager::getInstance()->addRule($taskName, $item['tick_exp'], function () use($taskName, $item) {
                // 设置$concurrent =1 就相当于阻塞模式了，轮训一个一个消费
                $runner = CommandRunner::getInstance($taskName,1);
                if($runner->isNextHandle()) {
                    $execFile = $item['cli_command'];
                    $params = [
                        '--type=proc',
                        '--name=bingcool-'.$taskName
                    ];

                    // 调用命令程序
                    $runner->procOpen(function ($pipe0, $pipe1, $pipe2, $status, $returnCode) {
                        $buffer = fgets($pipe1, 8192);
                        var_dump(json_decode($buffer, true) ?? $buffer);
                        //var_dump('returnCode='.$returnCode);
                    } , $execFile, $params);
                }
            });
        }

    }


}
