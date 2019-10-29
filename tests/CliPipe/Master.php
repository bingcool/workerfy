<?php
define("START_SCRIPT_FILE", __FILE__);
date_default_timezone_set('Asia/Shanghai');

$pid_file = __DIR__.'/'.pathinfo(__FILE__)['filename'].'.pid';
$log_file = __DIR__.'/'.pathinfo(__FILE__)['filename'].'.log';

define("PID_FILE", $pid_file);
define("CTL_LOG_FILE", $log_file);
$dir_config = dirname(__DIR__);
$root_path = dirname($dir_config);

include $root_path.'/src/Ctrl.php';

include $root_path."/vendor/autoload.php";

$config_file_path = $dir_config."/Config/config.php";

$Config = \Workerfy\Config::getInstance();
$Config->loadConfig($config_file_path);

$processManager = \Workerfy\processManager::getInstance();

$process_name = 'test-cli-pipe';
$process_class = \Workerfy\Tests\CliPipe\Worker::class;
$process_worker_num = defined("WORKER_NUM") ? defined("WORKER_NUM") : 3;
$async = true;
$args = [
    'wait_time' => 1,
    //'user' => 'bingcoolv'
];
$extend_data = null;
// 设置启用管道，默认不设置
$processManager->createCliPipe(true);
$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);

$processManager->onStart = function ($pid) {
    file_put_contents(PID_FILE, $pid);
};

$processManager->onCreateDynamicProcess = function ($process_name, $num) {
  $this->createDynamicProcess($process_name, $num);
};
// 终端信息处理
$processManager->onCliMsg = function($msg) {
    var_dump("父进程收到来自于cli终端信息：".$msg);
};

$processManager->onExit = function() use($config_file_path) {
    //var_dump("master exit",$config_file_path);
};

$master_pid = $processManager->start();
