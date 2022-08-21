#!/usr/bin/php
<?php
require dirname(__DIR__).'/Common.php';

// 设置进程间通信队列
$msg_queue_name = 'order';
define("MSG_QUEUE_NAME_ORDER", $msg_queue_name);
$sysvmsgManager = \Workerfy\Memory\SysvmsgManager::getInstance();
$sysvmsgManager->addMsgFtok(MSG_QUEUE_NAME_ORDER, __FILE__, 'o');
$sysvmsgManager->registerMsgType(MSG_QUEUE_NAME_ORDER,"add_order",2);

$processManager = \Workerfy\ProcessManager::getInstance();
$process_name = 'test-yield';
$process_class = \Workerfy\Tests\Myield\Worker::class;
$process_worker_num = 1;
$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;

$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);


$processManager->onStart = function ($pid) {
    // file_put_contents(PID_FILE, $pid);
};

$processManager->onExit = function() {
    //var_dump("master exit",$configFilePath);
};

$master_pid = $processManager->start();
