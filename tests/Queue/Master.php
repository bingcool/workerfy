#!/usr/bin/php

<?php
require dirname(__DIR__).'/Common.php';

// redis 队列消费
$processManager = \Workerfy\ProcessManager::getInstance();

//$process_name = 'test-queue';
//$process_class = \Workerfy\Tests\Queue\QueueConsumerWorker::class;
//$process_worker_num = 2; // 启动两个子进程，worker_id 分别为0 ，1
//$async = true;
//$args = [
//    'wait_time' => 1
//];
//$extend_data = null;
//$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);


// redis延迟队列消费
$processManager = \Workerfy\ProcessManager::getInstance();
$process_name = 'test-delay-queue';
$process_class = \Workerfy\Tests\Queue\DelayConsumerWorker::class;
$process_worker_num = 2; // 启动两个子进程，worker_id 分别为0 ，1
$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;
$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);


$processManager->onStart = function ($pid) {
    file_put_contents(PID_FILE, $pid);
};

$processManager->onExit = function() use($configFilePath) {
    var_dump("master exit",$configFilePath);
};

$master_pid = $processManager->start();
