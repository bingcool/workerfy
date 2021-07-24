#!/usr/bin/php
<?php
require dirname(__DIR__).'/Common.php';

$processManager = \Workerfy\processManager::getInstance();
$process_name = 'test-pipe';
$process_class = \Workerfy\Tests\Pipe\Worker::class;
$process_worker_num = 1;
$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;

// 设置启用管道，默认不设置
$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);

$processManager->onStart = function ($pid) {
    file_put_contents(PID_FILE, $pid);
};

// 父进程收到某个子进程的信息，同时向该子进程回复信息
$processManager->onPipeMsg = function($msg, $from_process_name, $from_process_worker_id) use($processManager) {
    $array = [
        $msg,
        $from_process_name,
        $from_process_worker_id,
    ];
    var_dump("父进程收到信息 : ". $msg);

    $processManager->writeByProcessName($from_process_name, '子进程'.$from_process_name.'@'.$from_process_worker_id. ' 你好，我已收到你的信息');

    var_dump("父进程开始向子进程回复信息.....");
};

$processManager->onExit = function() use($configFilePath) {
    //var_dump("master exit",$configFilePath);
};

$master_pid = $processManager->start();
