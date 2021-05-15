#!/usr/bin/php
<?php
require dirname(__DIR__).'/Common.php';

$processManager = \Workerfy\processManager::getInstance();
$process_name = 'test-uuid-service';
$process_class = \Workerfy\Tests\UuidService\Worker::class;
$process_worker_num = getenv('worker_num') ? getenv('worker_num') : 2;
$async = true;
$args = [
    'wait_time' => 1,
    //'user' => 'bingcoolv',
    //'max_worker_num' => 10
];

$extend_data = null;

$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);

$processManager->onStart = function ($pid) {
    file_put_contents(PID_FILE, $pid);
};

$processManager->onExit = function() use($configFilePath) {
    //var_dump("master exit",$configFilePath);
};

$master_pid = $processManager->start();
