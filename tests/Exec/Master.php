#!/usr/bin/php
<?php
require dirname(__DIR__).'/Common.php';

$processManager = \Workerfy\processManager::getInstance([
    'report_status_tick_time' => 5
]);


$process_name = 'test-exec';
$process_class = \Workerfy\Tests\Exec\ExecWorker\Worker::class;
$process_worker_num = 2;
$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;

$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);


$process_name = 'test-procOpen';
$process_class = \Workerfy\Tests\Exec\ProcWorker\WorkerProc::class;
$process_worker_num = 2;
$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;

$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);


$processManager->onStart = function ($pid) {};
$processManager->onExit = function() use($configFilePath) {};
$master_pid = $processManager->start();
