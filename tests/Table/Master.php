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


$table = \Workerfy\Memory\TableManager::getInstance()->addTable('redis-table', [
    'size' => 4,
    'conflict_proportion' => 0.2,
    // 字段
    'fields'=> [
        ['tick_tasks','string', 8096]
    ]
]);

// 父进程设置值，看子进程是否能读到
$value = "hello, 我是父进程，我设置了table值";
$table->set('redis_test_data', [
    'tick_tasks'=>$value
]);
var_dump("父进程首次设置table的值: ".$value);
$processManager = \Workerfy\ProcessManager::getInstance();
$process_name = 'test-swoole-table';
$process_class = \Workerfy\Tests\Table\Worker::class;
$process_worker_num = 2;
$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;

$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);

$processManager->onStart = function ($pid) {
    file_put_contents(PID_FILE, $pid);
};

// 父进程读取子进程重新设置的值，看是否能读到
$processManager->onPipeMsg = function($msg, $from_process_name, $from_process_worker_id) {
    //var_dump($msg);
//    go(function() {
//        var_dump("master coroutine");
//    });
    $table = \Workerfy\Memory\TableManager::getInstance()->getTable('redis-table');
    $value = $table->get('redis_test_data','tick_tasks');
    var_dump($this->getMasterWorkerName().'@'.$this->getMasterWorkerId().'父进程读取到table的值'.' : '.$value);
};

$processManager->onExit = function() use($config_file_path) {
    //var_dump("master exit",$config_file_path);
};

$master_pid = $processManager->start();
