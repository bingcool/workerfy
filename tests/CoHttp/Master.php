#!/usr/bin/php
<?php
require dirname(__DIR__).'/Common.php';

$processManager = \Workerfy\processManager::getInstance();
$process_name = 'test-cli-pipe';
$process_class = \Workerfy\Tests\CoHttp\Worker::class;
$process_worker_num = getenv('worker_num') ? getenv('worker_num') : 1;
$async = true;
$args = [
    'wait_time' => 1,
    //'user' => 'bingcoolv',
    //'max_worker_num' => 10
];

$extend_data = null;
// 设置启用管道，默认不设置
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
