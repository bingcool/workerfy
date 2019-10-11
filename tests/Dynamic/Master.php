<?php

$pid_file = __DIR__.'/'.pathinfo(__FILE__)['filename'].'.pid';

define("PID_FILE", $pid_file);
$dir_config = dirname(__DIR__);
$root_path = dirname($dir_config);

include $root_path.'/src/Ctrl.php';

include $root_path."/vendor/autoload.php";

$config_file_path = $dir_config."/Config/config.php";

$Config = \Workerfy\Config::getInstance();
$Config->loadConfig($config_file_path);

$processManager = \Workerfy\processManager::getInstance();

// 业务进程
$process_name = 'test-dynamic';
$process_class = \Workerfy\Tests\Dynamic\Worker::class;
$process_worker_num = 1;
$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;

$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);

// 监控进程

$monitor_process_name = 'test-monitor';
$monitor_process_class = \Workerfy\Tests\Dynamic\Monitor_Worker::class;
$monitor_process_worker_num = 1;
$monitor_async = true;
$monitor_args = [
    'wait_time' => 1
];
$monitor_extend_data = null;

$processManager->addProcess($monitor_process_name, $monitor_process_class, $monitor_process_worker_num, $monitor_async, $monitor_args, $monitor_extend_data);


$processManager->onStart = function ($pid) {
    file_put_contents(PID_FILE, $pid);
};

// 父进程收到监控进程的动态创建业务进程指令
$processManager->onCreateDynamicProcess = function() use($process_name) {
    $this->createDynamicProcess($process_name);
};

// 父进程收到监控进程的动态销毁进程命令
$processManager->onDestroyDynamicProcess = function () use($process_name) {
    $this->destroyDynamicProcess($process_name);
};

// 父进程退出，只有子进程全部退出后，父进程才会退出
$processManager->onExit = function() use($config_file_path) {
    var_dump("master exit",$config_file_path);
};

$master_pid = $processManager->start();
