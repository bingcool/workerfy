#!/usr/bin/php
<?php
require dirname(__DIR__).'/Common.php';

$processManager = \Workerfy\ProcessManager::getInstance();
$process_name = 'test-openssl';
$process_class = \Workerfy\Tests\Openssl\Worker::class;
$process_worker_num = 1;
$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;

$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);

$processManager->onExit = function() {
    var_dump("master exit", $configFilePath);
};

$processManager->onHandleException = function($t) {
    //var_dump("aaaaaaaaaaaaaaaa");
};

$master_pid = $processManager->start();