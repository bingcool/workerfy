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

$processManager = \Workerfy\ProcessManager::getInstance();
$process_name = 'test-go-wait-group';
$process_class = \Workerfy\Tests\GoWaitGroup\Worker::class;
$process_worker_num = 2;
$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;

$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);

$processManager->onStart = function ($pid) {
    go(function () {
        var_dump("start master");
    });
    file_put_contents(PID_FILE, $pid);
};

$processManager->onExit = function() use($config_file_path) {
    //var_dump("master exit",$config_file_path);
};

$master_pid = $processManager->start();
