#! /usr/bin/php
<?php
define("START_SCRIPT_FILE", __FILE__);
date_default_timezone_set('Asia/Shanghai');

$pid_file = __DIR__.'/'.pathinfo(__FILE__)['filename'].'.pid';
$log_file = __DIR__.'/'.pathinfo(__FILE__)['filename'].'.log';
$status_file = __DIR__.'/'.pathinfo(__FILE__)['filename'].'.txt';

define("PID_FILE", $pid_file);
define("CTL_LOG_FILE", $log_file);
define("STATUS_FILE", $status_file);

// 可以定义全局变量改变上报状态时间间隔，单位秒
define("WORKERFY_REPORT_TICK_TIME", 10);

$dir_config = dirname(__DIR__);
$root_path = dirname($dir_config);

include $root_path.'/src/Ctrl.php';

include $root_path."/vendor/autoload.php";

$config_file_path = $dir_config."/Config/config.php";

$Config = \Workerfy\Config::getInstance();
$Config->loadConfig($config_file_path);

$processManager = \Workerfy\processManager::getInstance();

$process_name = 'test-report-status';
$process_class = \Workerfy\Tests\ReportStatus\Worker::class;
$process_worker_num = 3;
$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;
$processManager->createCliPipe(false);
$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);

$processManager->onStart = function ($pid) {

};

// 状态上报
$processManager->onReportStatus = function($status) {
    file_put_contents(STATUS_FILE, json_encode($status, JSON_UNESCAPED_UNICODE));
    file_put_contents(__DIR__.'/test/test.log','hello word');
    // 可以通过http发送保存mysql等
};


$processManager->onExit = function() use($config_file_path) {
    //var_dump("master exit",$config_file_path);
};

$master_pid = $processManager->start();
