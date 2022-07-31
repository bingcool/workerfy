#!/usr/bin/php
<?php
require dirname(__DIR__).'/Common.php';

$processManager = \Workerfy\ProcessManager::getInstance();
$process_name = 'test-http';
$process_class = \Workerfy\Tests\CoHttp\Worker::class;
$process_worker_num = getenv('worker_num') ? getenv('worker_num') : 1;
$async = true;
$args = [
    'wait_time' => 1,
];

$extend_data = null;
$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);
$processManager->onStart = function ($pid) {
    file_put_contents(PID_FILE, $pid);
};

$processManager->onCreateDynamicProcess = function ($process_name, $num) {
    $this->createDynamicProcess($process_name, $num);
};
// 终端信息处理
$processManager->onCliMsg = function($msg) {
    //var_dump("父进程收到来自于cli终端信息：".$msg);
};

$processManager->onExit = function() use($configFilePath) {
    //var_dump("master exit",$configFilePath);
};

$master_pid = $processManager->start();