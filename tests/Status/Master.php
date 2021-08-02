#!/usr/bin/php
<?php
require dirname(__DIR__).'/Common.php';

ini_set('memory_limit','20M');

$processManager = \Workerfy\processManager::getInstance();
$process_name = 'test-status';
$process_class = \Workerfy\Tests\Status\Worker::class;
$process_worker_num = 3;
$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;
$processManager->enableCliPipe(true);
$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);

$processManager->onStart = function ($pid) {

    file_put_contents(PID_FILE, $pid);

};



$processManager->onExit = function() {
    //var_dump("master exit");
};

var_dump(ini_get('memory_limit'));

$master_pid = $processManager->start();
