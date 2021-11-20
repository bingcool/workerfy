#!/usr/bin/php
<?php
require dirname(__DIR__).'/Common.php';

// 设置进程间通信队列
$msg_queue_name = 'order';
define("MSG_QUEUE_NAME_ORDER", $msg_queue_name);
$sysvmsgManager = \Workerfy\Memory\SysvmsgManager::getInstance();
$sysvmsgManager->addMsgFtok(MSG_QUEUE_NAME_ORDER, __FILE__, 'o');
$sysvmsgManager->registerMsgType(MSG_QUEUE_NAME_ORDER,"add_order",2);

// 添加多一个队列，不注册事件类型，则投递在默认的消息类型中
$sysvmsgManager->addMsgFtok('user', __FILE__, 'v');

$processManager = \Workerfy\ProcessManager::getInstance();
$process_name = 'test-sysvmsg';
$process_class = \Workerfy\Tests\Sysvmsg\Worker::class;
$process_worker_num = 4;
$async = true;
$args = [
    'wait_time' => 1
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
