#!/usr/bin/php
<?php
require dirname(__DIR__).'/Common.php';

ini_set('display_errors','on');

set_error_handler(function ($error_type, $error_msg, $error_file, $error_line) {
    $code = E_USER_WARNING;
    var_dump($error_type, $error_line);
});

var_dump($a);

exit;

// Redis Pool
$processManager = \Workerfy\processManager::getInstance();
$process_name = 'test-pool-redis';
$process_class = \Workerfy\Tests\Pool\RedisPoolWorker::class;
$process_worker_num = 1;
$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;
//$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);


// Mysql Pool
$processManager = \Workerfy\processManager::getInstance();
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
