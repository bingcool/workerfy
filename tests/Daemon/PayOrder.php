<?php

$dir_config = dirname(__DIR__);

$vendor_path = dirname($dir_config);

include $vendor_path."/vendor/autoload.php";

$config_file_path = $dir_config."/Config/config.php";

\Workerfy\Config::getInstance()->loadConfig($config_file_path);

$processManager = \Workerfy\processManager::getInstance();

$process_name = 'worker';
$process_class = \Workerfy\Tests\Daemon\Worker1::class;
$process_worker_num = 2;
$async = true;
$args = [];
$extend_data = null;

$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);


$processManager->onPipeMsg = function($msg, $from_process_name, $from_process_worker_id, $is_proxy_by_master) {
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

$processManager->onExit = function() use($config_file_path) {
    var_dump("master exit",$config_file_path);
};

$processManager->start();





//$processManager->writeByProcessName('worker', 'this message from master worker');
