#!/usr/bin/php
<?php
require dirname(__DIR__).'/Common.php';

$processManager = \Workerfy\processManager::getInstance();
$woker_process_name = 'worker-daemon-test';
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

