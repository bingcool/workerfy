<?php
namespace Workerfy\tests\Log;

use Workerfy\Log\LogManager;

class Worker extends \Workerfy\AbstractProcess {

    public $callNum = 0;

    public $startTime;
    public $runTime = 20;

    public function init() {
        var_dump('init function');
        $this->startTime = time();
    }

    public function run() {
        \Workerfy\Coroutine\GoCoroutine::go(function ($name, $sex) {
            //var_dump($name, $sex);
            $logManager = \Workerfy\Log\LogManager::getInstance()->getLogger(LogManager::RUNTIME_ERROR_TYPE);
            $logManager->info('coroutine create',['name' => $name, 'sex'=>$sex]);

            defer(function () {
                var_dump(\Co::getCid());
            });

        }, $name ='bingcool', $sex=1);
    }

}