#!/usr/bin/php
<?php
require dirname(__DIR__).'/Common.php';


// 生产进程
$processManager = \Workerfy\processManager::getInstance();
$process_name = 'test-kafka-produce';
$process_class = \Workerfy\Tests\Kafka\ProduceWorker::class;
$process_worker_num = 1;
$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;
$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);


// 多进程消费
$processManager = \Workerfy\processManager::getInstance();
$process_name = 'test-kafka-consumer';
$process_class = \Workerfy\Tests\Kafka\ConsumerWorker::class;
$process_worker_num = 2;
$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;
$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);




$processManager->onStart = function ($pid) {

};

$processManager->onReportStatus = function($status) {

};

$processManager->onExit = function() use($configFilePath) {
    //var_dump("master exit",$configFilePath);
};

$master_pid = $processManager->start();
