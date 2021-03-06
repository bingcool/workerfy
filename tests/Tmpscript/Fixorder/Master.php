#!/usr/bin/php
<?php
require dirname(dirname(__DIR__)).'/Common.php';

$processManager = \Workerfy\processManager::getInstance();
$process_name = 'test-tmp-script';
$process_class = \Workerfy\Tests\Tmpscript\Fixorder\Worker::class;
$process_worker_num = 1;
$async = true;
$args = [
    'wait_time' => 1,
    'user'=>'bingcool'
];
$extend_data = null;

$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);


$processManager->onStart = function ($pid) {
    file_put_contents(PID_FILE, $pid);
};

$processManager->onExit = function() {
    //var_dump("master exit");
};

$master_pid = $processManager->start();
