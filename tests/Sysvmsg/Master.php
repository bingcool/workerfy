#!/usr/bin/php
<?php
date_default_timezone_set('Asia/Shanghai');

define("START_SCRIPT_FILE", __FILE__);
// 默认在当前目录runtime下
define("PID_FILE_ROOT", '/tmp/workerfy/log/Sysvmsg/');
$pid_file = PID_FILE_ROOT.'/'.pathinfo(__FILE__)['filename'].'.pid';
define("PID_FILE", $pid_file);

$dirConfigPath = dirname(__DIR__);
$rootPath = dirname($dirConfigPath);
include $rootPath."/vendor/autoload.php";
$configFilePath = $dirConfigPath."/Config/config.php";

$Config = \Workerfy\ConfigLoad::getInstance();
$Config->loadConfig($configFilePath);

// 设置进程间通信队列
$msg_queue_name = 'order';
define("MSG_QUEUE_NAME_ORDER", $msg_queue_name);
$sysvmsgManager = \Workerfy\Memory\SysvmsgManager::getInstance();
$sysvmsgManager->addMsgFtok(MSG_QUEUE_NAME_ORDER, __FILE__, 'o');
$sysvmsgManager->registerMsgType(MSG_QUEUE_NAME_ORDER,"add_order",2);

// 添加多一个队列
$sysvmsgManager->addMsgFtok('user', __FILE__, 'v');

$processManager = \Workerfy\processManager::getInstance();
$process_name = 'test-sysvmsg';
$process_class = \Workerfy\Tests\Sysvmsg\Worker::class;
$process_worker_num = 3;
$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;

$processManager->createClipipe(true);
$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);


$processManager->onStart = function ($pid) {
    file_put_contents(PID_FILE, $pid);
};

$processManager->onExit = function() use($config_file_path) {
    //var_dump("master exit",$config_file_path);
};

$master_pid = $processManager->start();
