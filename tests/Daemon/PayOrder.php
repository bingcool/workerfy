#!/usr/bin/php
<?php
define("START_SCRIPT_ROOT", __DIR__);
define("START_SCRIPT_FILE", __FILE__);
date_default_timezone_set('Asia/Shanghai');

// 默认在当前目录runtime下
define("PID_FILE_ROOT", '/tmp/workerfy/log/Daemon');
// 不存在则创建
if(!is_dir(PID_FILE_ROOT)) {
    mkdir(PID_FILE_ROOT,0777,true);
}
$pid_file = PID_FILE_ROOT.'/'.pathinfo(__FILE__)['filename'].'.pid';
define("PID_FILE", $pid_file);


$dir_config = dirname(__DIR__);
$root_path = dirname($dir_config);


############### 启动 ###################
include $root_path."/vendor/autoload.php";

$config_file_path = $dir_config."/Config/config.php";

$Config = \Workerfy\ConfigLoad::getInstance();
$Config->loadConfig($config_file_path);

$processManager = \Workerfy\processManager::getInstance();

$woker_process_name = 'worker';
$process_class = \Workerfy\Tests\Daemon\Worker::class;
$process_worker_num = 10;
$async = true;
$args = [
    'wait_time' => 10
];
$extend_data = null;

$processManager->addProcess($woker_process_name, $process_class, $process_worker_num, $async, $args, $extend_data);

$monitor_process_name = 'monitor';
$process_class = \Workerfy\Tests\Daemon\Monitor::class;
$process_worker_num = 1;
$async = true;
$args = ['wait_time' => 10];
$extend_data = null;
$processManager->addProcess($monitor_process_name, $process_class, $process_worker_num, $async, $args, $extend_data);


$processManager->onStart = function ($pid) {
    file_put_contents(PID_FILE, $pid);
};

$processManager->onPipeMsg = function($msg, $from_process_name, $from_process_worker_id) use($Config) {
    $array = [
        $msg,
        $from_process_name,
        $from_process_worker_id,
    ];

    var_dump($array);
};

$processManager->onProxyMsg = function($msg, $from_process_name, $from_process_worker_id, $to_process_name, $to_process_worker_id) {
    $array = [
        $msg,
        $from_process_name,
        $from_process_worker_id,
        $to_process_name,
        $to_process_worker_id
    ];
    $this->writeByMasterProxy($msg, $from_process_name, $from_process_worker_id, $to_process_name, $to_process_worker_id);
    var_dump($array);
};

$processManager->onCreateDynamicProcess = function() use($woker_process_name) {
    $this->createDynamicProcess($woker_process_name);
};

$processManager->onDestroyDynamicProcess = function () use($woker_process_name) {
    $this->destroyDynamicProcess($woker_process_name);
};

$processManager->onHandleException = function (\Exception $e) {
    var_dump($e->getMessage());
};

$processManager->onExit = function() use($config_file_path) {
    var_dump("master exit",$config_file_path);
};

$master_pid = $processManager->start();



//$processManager->writeByProcessName('worker', 'this message from master worker');

//$processManager->broadcastProcessWorker('worker', 'this message from master worker');

