#!/usr/bin/php
<?php
define("START_SCRIPT_ROOT", __DIR__);
define("START_SCRIPT_FILE", __FILE__);
date_default_timezone_set('Asia/Shanghai');

// 默认在当前目录runtime下
define("PID_FILE_ROOT", START_SCRIPT_ROOT.'/runtime');
$pid_file = PID_FILE_ROOT.'/'.pathinfo(__FILE__)['filename'].'.pid';
$log_file = PID_FILE_ROOT.'/'.pathinfo(__FILE__)['filename'].'.log';
$status_file = PID_FILE_ROOT.'/'.pathinfo(__FILE__)['filename'].'.status';

// 不存在则创建
if(!is_dir(PID_FILE_ROOT)) {
    mkdir(PID_FILE_ROOT,0777);
}

define("PID_FILE", $pid_file);
define("CTL_LOG_FILE", $log_file);
define("STATUS_FILE", $status_file);


$dir_config = dirname(__DIR__);
$root_path = dirname($dir_config);

include $root_path.'/src/Ctrl.php';

include $root_path."/vendor/autoload.php";

$config_file_path = $dir_config."/Config/config.php";

$Config = \Workerfy\Config::getInstance();
$Config->loadConfig($config_file_path);

$processManager = \Workerfy\processManager::getInstance();

$process_name = 'test-proxy';
$process_class = \Workerfy\Tests\Pipe\Worker::class;
$process_worker_num = 1;
$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;
// 设置启用管道，默认不设置
$processManager->createCliPipe(true);
$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);


$processManager->onStart = function ($pid) {
    file_put_contents(PID_FILE, $pid);
};

// 父进程收到某个子进程的信息，同时向该子进程回复信息
$processManager->onPipeMsg = function($msg, $from_process_name, $from_process_worker_id) use($processManager) {
    $array = [
        $msg,
        $from_process_name,
        $from_process_worker_id,
    ];
    var_dump("父进程收到信息 : ". $msg);

    $processManager->writeByProcessName($from_process_name, '子进程'.$from_process_name.'@'.$from_process_worker_id. ' 你好，我已收到你的信息');

    var_dump("父进程开始向子进程回复信息.....");
};

$processManager->onExit = function() use($config_file_path) {
    //var_dump("master exit",$config_file_path);
};

$master_pid = $processManager->start();
