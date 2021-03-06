#!/usr/bin/php
<?php
require dirname(__DIR__).'/Common.php';

// 用户业务注册log操作对象
$logManager = \Workerfy\Log\LogManager::getInstance()->registerLogger('default', __DIR__.'/'.pathinfo(__FILE__)['filename'].'.log');

$processManager = \Workerfy\processManager::getInstance();
$process_name = 'test-logger-test';
$process_class = \Workerfy\Tests\Log\Worker::class;

$process_worker_num = getenv('num') ?: 2;

$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;
// 设置启用管道，默认不设置
$processManager->createCliPipe(true);
$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);


$processManager->onStart = function ($pid) {
    $logger = \Workerfy\Log\LogManager::getInstance()->getLogger();
    $logger->info('中国有{num}人口',['num'=>10000000000],false);

    \Workerfy\Coroutine\GoCoroutine::go(function () use($pid) {
        $db = \Workerfy\Tests\Db::getMasterMysql();
        $query = $db->query("select * from user limit 1");
        $res = $query->fetchAll(\PDO::FETCH_ASSOC);  //获取结果集中的所有数据
        var_dump($res);
    });
};

// 注册运行时的错误记录日志
$processManager->onRegisterRuntimeLog = function () {
    $logger = \Workerfy\Log\LogManager::getInstance()->getLogger(\Workerfy\Log\LogManager::RUNTIME_ERROR_TYPE);
    if(!is_object($logger)) {
        $pid_file_root = pathinfo(PID_FILE)['dirname'];
        $runtime_log = $pid_file_root.'/runtime.log';
        $logger = \Workerfy\Log\LogManager::getInstance()->registerLogger(\Workerfy\Log\LogManager::RUNTIME_ERROR_TYPE, $runtime_log);
    }
    $logger->info("默认Runtime日志注册成功",[],false);
    return $logger;
};

$processManager->onExit = function() use($configFilePath) {
    //var_dump("master exit", $configFilePath);
};

$master_pid = $processManager->start();
