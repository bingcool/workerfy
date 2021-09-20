#!/usr/bin/php
<?php
require dirname(__DIR__).'/Common.php';

ini_set('display_errors','on');

// Redis Pool
$processManager = \Workerfy\ProcessManager::getInstance();
$process_name = 'test-pool-redis';
$process_class = \Workerfy\Tests\Pool\RedisPoolWorker::class;
$process_worker_num = 1;
$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;
$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);


// Mysql Pool
$process_name = 'test-pool-mysql';
$process_class = \Workerfy\Tests\Pool\MysqlPoolWorker::class;
$process_worker_num = 1;
$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;
//$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);


// Curl Pool
$processManager = \Workerfy\processManager::getInstance();
$process_name = 'test-pool-curl';
$process_class = \Workerfy\Tests\Pool\CurlPoolWorker::class;
$process_worker_num = 1;
$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;
$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);


$processManager->onStart = function ($pid) {
    file_put_contents(PID_FILE, $pid);
};

$master_pid = $processManager->start();
