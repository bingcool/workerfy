#!/usr/bin/php
<?php
require dirname(__DIR__).'/Common.php';

$processManager = \Workerfy\ProcessManager::getInstance([
    'report_status_tick_time' => 5
]);
$process_name = 'test-go-coroutine-timer';
$process_class = \Workerfy\Tests\Gotimer\Worker::class;
$process_worker_num = 1;
$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;

$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);
$processManager->onStart = function ($pid) {};
$processManager->onExit = function() {};
$master_pid = $processManager->start();
