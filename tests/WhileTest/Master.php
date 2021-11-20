#!/usr/bin/php
<?php
require dirname(__DIR__).'/Common.php';

$processManager = \Workerfy\ProcessManager::getInstance();
$process_name = 'test-while-test';
$process_class = \Workerfy\Tests\WhileTest\Worker::class;
$process_worker_num = getenv('num') ?: 1;

$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;

$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);


$processManager->onStart = function ($pid) {
    file_put_contents(PID_FILE, $pid);
};
$processManager->onExit = function() {};
$master_pid = $processManager->start();
