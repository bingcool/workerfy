#!/usr/bin/php
<?php
require dirname(__DIR__).'/Common.php';

$processManager = \Workerfy\processManager::getInstance();
$process_name = 'test-db-sleep';
$process_class = \Workerfy\Tests\DbSleep\Worker::class;
$process_worker_num = 2;
$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;
$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);


$processManager->onStart = function ($pid) {
};

// 状态上报
$processManager->onReportStatus =  function ($status) {
    var_dump("master status");
    // 需要运行在协程中
    go(function () {
        $db = \Workerfy\Tests\Make::makeCommonDb();
        $res = $db->createCommand("select sleep(10)")->queryAll();
        var_dump($res);
    });
};


$processManager->onExit = function() use($configFilePath) {
    //var_dump("master exit",$configFilePath);
};

$master_pid = $processManager->start();
