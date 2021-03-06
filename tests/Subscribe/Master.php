#!/usr/bin/php
<?php
require dirname(__DIR__).'/Common.php';

ini_set('memory_limit','20M');

$processManager = \Workerfy\processManager::getInstance();
$process_name = 'test-subscribe';
$process_class = \Workerfy\Tests\Subscribe\Worker::class;

// 订阅的话只需要一个进程订阅，多个的话会重复订阅，重复处理逻辑，造成业务错误
$process_worker_num = 1;
$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;
$processManager->createCliPipe(true);
$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);

$processManager->onStart = function ($pid) {

    file_put_contents(PID_FILE, $pid);

};

$processManager->onCreateDynamicProcess = function ($process_name, $process_num) use($processManager) {
    $this->createDynamicProcess($process_name, $process_num);
};


$processManager->onExit = function()  {
    //var_dump("master exit");
};

$master_pid = $processManager->start();
