#!/usr/bin/php
<?php
define("START_SCRIPT_ROOT", __DIR__);
define("START_SCRIPT_FILE", __FILE__);
date_default_timezone_set('Asia/Shanghai');

// 默认在当前目录runtime下
define("PID_FILE_ROOT", '/tmp/workerfy/log/Tmpscript/Fixorder');
// 不存在则创建
if(!is_dir(PID_FILE_ROOT)) {
    mkdir(PID_FILE_ROOT,0777,true);
}
$pid_file = PID_FILE_ROOT.'/'.pathinfo(__FILE__)['filename'].'.pid';
define("PID_FILE", $pid_file);

// 这里的文件夹位置需要再上一层级
$dir_config = dirname(dirname(__DIR__));
$root_path = dirname($dir_config);

include $root_path . '/src/Ctrl.php';

include $root_path . "/vendor/autoload.php";

$config_file_path = $dir_config."/Config/config.php";

$Config = \Workerfy\ConfigLoad::getInstance();
$Config->loadConfig($config_file_path);

$processManager = \Workerfy\processManager::getInstance();
$process_name = 'test-tmp-script';
$process_class = \Workerfy\Tests\Tmpscript\Fixorder\Worker::class;
$process_worker_num = 1;
$async = true;
$args = [
    'wait_time' => 1,
    'user'=>'bingcool'
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
