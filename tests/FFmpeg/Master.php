#!/usr/bin/php
<?php
require dirname(__DIR__).'/Common.php';

$processManager = \Workerfy\processManager::getInstance([
    'report_status_tick_time' => 5
]);
$process_name = 'test-FFmpeg';
$process_class = \Workerfy\Tests\FFmpeg\Worker::class;
$process_worker_num = 1;
$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;

$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);
$processManager->onStart = function ($pid) {};
$processManager->onExit = function() use($configFilePath) {};
$master_pid = $processManager->start();
