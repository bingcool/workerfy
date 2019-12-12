#!/usr/bin/php
<?php
define("START_SCRIPT_ROOT", __DIR__);
define("START_SCRIPT_FILE", __FILE__);
date_default_timezone_set('Asia/Shanghai');

// 默认在当前目录runtime下
define("PID_FILE_ROOT", '/tmp/workerfy/log/Clipipe');
// 不存在则创建
if(!is_dir(PID_FILE_ROOT)) {
    mkdir(PID_FILE_ROOT,0777,true);
}
$pid_file = PID_FILE_ROOT.'/'.pathinfo(__FILE__)['filename'].'.pid';
define("PID_FILE", $pid_file);


$dir_config = dirname(__DIR__);
$root_path = dirname($dir_config);

include $root_path.'/src/Ctrl.php';

include $root_path."/vendor/autoload.php";

$config_file_path = $dir_config."/Config/config.php";

$Config = \Workerfy\ConfigLoad::getInstance();
$Config->loadConfig($config_file_path);

$processManager = \Workerfy\processManager::getInstance();

$process_name = 'test-cli-pipe';
$process_class = \Workerfy\Tests\CliPipe\Worker::class;
$process_worker_num = defined("WORKER_NUM") ? defined("WORKER_NUM") : 3;
$async = true;
$args = [
    'wait_time' => 1,
    //'user' => 'bingcoolv',
    //'max_worker_num' => 10
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
    //var_dump("父进程收到来自于cli终端信息：".$msg);
};

$processManager->onExit = function() use($config_file_path) {
    //var_dump("master exit",$config_file_path);
};

$master_pid = $processManager->start();
