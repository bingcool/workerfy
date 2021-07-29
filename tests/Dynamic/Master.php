#!/usr/bin/php
<?php
require dirname(__DIR__).'/Common.php';

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

$processManager->addProcess(
    $process_name,
    $process_class,
    $process_worker_num,
    $async,
    $args,
    $extend_data
);

// 监控进程
$monitor_process_name = 'test-monitor';
$monitor_process_class = \Workerfy\Tests\Dynamic\Monitor_Worker::class;
$monitor_process_worker_num = 1;
$monitor_async = true;
$monitor_args = [
    'wait_time' => 20,
    'monitor_process_name' => $process_name //监听的关联的动态进程名称
];
$monitor_extend_data = null;

$processManager->addProcess(
    $monitor_process_name,
    $monitor_process_class,
    $monitor_process_worker_num,
    $monitor_async,
    $monitor_args,
    $monitor_extend_data
);


$processManager->onStart = function ($pid) {
    file_put_contents(PID_FILE, $pid);
};

// 父进程收到监控进程的动态创建业务进程指令
$processManager->onCreateDynamicProcess = function($dynamic_process_name, $dynamic_process_num) {
    var_dump('master receive :'. 'start create 动态进程');
    $this->createDynamicProcess($dynamic_process_name, $dynamic_process_num);
};

// 父进程收到监控进程的动态销毁进程命令
$processManager->onDestroyDynamicProcess = function ($dynamic_process_name, $dynamic_process_num) {
    var_dump("master receive :". 'start destroy 动态进程');
    $this->destroyDynamicProcess($dynamic_process_name, $dynamic_process_num);
};

// 父进程退出，只有子进程全部退出后，父进程才会退出
$processManager->onExit = function() use($configFilePath) {
    //var_dump("master exit",$configFilePath);
};

$processManager->onHandleException = function(\Throwable $e) {
    //var_dump($e->getMessage());
};

$master_pid = $processManager->start();
