<?php

date_default_timezone_set('Asia/Shanghai');

$pid_file = __DIR__.'/'.pathinfo(__FILE__)['filename'].'.pid';

define("PID_FILE", $pid_file);
$dir_config = dirname(__DIR__);
$root_path = dirname($dir_config);

include $root_path.'/src/Ctrl.php';

include $root_path."/vendor/autoload.php";

$config_file_path = $dir_config."/Config/config.php";

$Config = \Workerfy\Config::getInstance();
$Config->loadConfig($config_file_path);


// 设置进程间通信队列
$msg_queue_name = 'order';
define("MSG_QUEUE_NAME_ORDER", $msg_queue_name);
$sysvmsgManager = \Workerfy\Memory\SysvmsgManager::getInstance();
$sysvmsgManager->addMsgFtok(MSG_QUEUE_NAME_ORDER, __FILE__, 'o');
$sysvmsgManager->registerMsgType(MSG_QUEUE_NAME_ORDER,"add_order",2);


$processManager = \Workerfy\processManager::getInstance();
$process_name = 'test-sysvmsg';
$process_class = \Workerfy\Tests\Sysvmsg\Worker::class;
$process_worker_num = 3;
$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;

$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);


$processManager->onStart = function ($pid) {
    file_put_contents(PID_FILE, $pid);
};

$processManager->onExit = function() use($config_file_path) {
    //var_dump("master exit",$config_file_path);
};

$master_pid = $processManager->start();
