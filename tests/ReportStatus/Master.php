#!/usr/bin/php
<?php
require dirname(__DIR__).'/Common.php';

// 可以定义全局变量改变上报状态时间间隔，单位秒
define("WORKERFY_REPORT_TICK_TIME", 2);

$processManager = \Workerfy\processManager::getInstance();
$process_name = 'test-report-status';
$process_class = \Workerfy\Tests\ReportStatus\Worker::class;
$process_worker_num = 2;
$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;
$processManager->createCliPipe(true);
$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);


//$processManager->writeByProcessName($process_name, 0);

$processManager->onStart = function ($pid) {
};

// 状态上报
$processManager->onReportStatus =  function ($status) {
    var_dump($status);

    file_put_contents(STATUS_FILE, json_encode($status, JSON_UNESCAPED_UNICODE));

    // 需要运行在协程中
    go(function () {
        $db = \Workerfy\Tests\Db::getMasterMysql();
        $query = $db->query("SELECT * FROM user LIMIT 1");
        $res = $query->fetchAll(\PDO::FETCH_ASSOC);  //获取结果集中的所有数据
        var_dump($res);
    });
};


$processManager->onExit = function() {

};

$master_pid = $processManager->start();
