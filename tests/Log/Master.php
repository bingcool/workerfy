#!/usr/bin/php
<?php

use Workerfy\Log\LogManager;

require dirname(__DIR__).'/Common.php';

// 用户业务注册log操作对象
$logManager = \Workerfy\Log\LogManager::getInstance()->registerLogger('default', __DIR__.'/'.pathinfo(__FILE__)['filename'].'.log');

$processManager = \Workerfy\ProcessManager::getInstance();
$process_name = 'test-logger-test';
$process_class = \Workerfy\Tests\Log\Worker::class;

$process_worker_num = getenv('num') ?: 2;

$async = true;
$args = [
    'wait_time' => 1,
];
$extend_data = null;

$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);


$processManager->onStart = function ($pid) {
    $logger = \Workerfy\Log\LogManager::getInstance()->getLogger(LogManager::RUNTIME_ERROR_TYPE);
    $logger->info('中国有{num}人口',['{num}'=>10000000000],false);

    \Workerfy\Coroutine\GoCoroutine::go(function () use($pid) {
        $db = \Workerfy\Tests\Make::makeMysql();
        $res = $db->query("select * from tbl_users limit 1");
        var_dump($res);
    });

};

// 注册运行时的错误记录日志 实际生产中$runtimeLog 最好设置在独立目录
$processManager->onRegisterLogger = function () {
    $logger = LogManager::getInstance()->getLogger(LogManager::RUNTIME_ERROR_TYPE);
    if(!is_object($logger)) {
        $pidFileRoot = pathinfo(PID_FILE,PATHINFO_DIRNAME);
        $runtimeLog = $pidFileRoot.'/runtime.log';
        $logger = LogManager::getInstance()->registerLogger(LogManager::RUNTIME_ERROR_TYPE, $runtimeLog);
    }
    $logger->info("默认Runtime日志注册成功",[],false);
    return $logger;
};

$processManager->onExit = function() '' {
    //var_dump("master exit", $configFilePath);
};

$master_pid = $processManager->start();
