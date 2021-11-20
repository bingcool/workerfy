#!/usr/bin/php
<?php

require dirname(__DIR__).'/Common.php';

// 创建进程管理实例
$processManager = \Workerfy\ProcessManager::getInstance();

// 注册日志
$processManager->onRegisterRuntimeLog = function ()
{
    $logger = \Workerfy\Log\LogManager::getInstance()->getLogger(\Workerfy\Log\LogManager::RUNTIME_ERROR_TYPE);
    if(!is_object($logger)) {
        $pidFileRoot = pathinfo(PID_FILE,PATHINFO_DIRNAME);
        $runtimeLog = $pidFileRoot.'/runtime.log';
        $logger = \Workerfy\Log\LogManager::getInstance()->registerLogger(\Workerfy\Log\LogManager::RUNTIME_ERROR_TYPE, $runtimeLog);
    }
    $logger->info("默认Runtime日志注册成功",[],false);
    return $logger;
};

$process_name = 'test-curl';
$process_class = \Workerfy\Tests\Curl\Worker::class;
$process_worker_num = 3;
$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;

$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);

$processManager->onExit = function() {

};

$processManager->onHandleException = function($t) {
    //var_dump("aaaaaaaaaaaaaaaa");
};

$master_pid = $processManager->start();