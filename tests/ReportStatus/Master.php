#!/usr/bin/php
<?php
require dirname(__DIR__).'/Common.php';

$processManager = \Workerfy\processManager::getInstance();
$process_name = 'test-report-status';
$process_class = \Workerfy\Tests\ReportStatus\Worker::class;
$process_worker_num = 2;
$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;

$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);


//$processManager->writeByProcessName($process_name, 0);

$processManager->onStart = function ($pid) {
};

// 状态上报
$processManager->onReportStatus =  function ($status) {
    //var_dump($status);

    file_put_contents(STATUS_FILE, json_encode($status, JSON_UNESCAPED_UNICODE));

    // 需要运行在协程中
    go(function () {
        $db = \Workerfy\Tests\Make::makeMysql();
        $res = $db->query("SELECT * FROM tbl_users LIMIT 1");
        var_dump($res);
    });
};


$processManager->onExit = function() {

};

$master_pid = $processManager->start();
