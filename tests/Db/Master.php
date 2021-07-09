#!/usr/bin/php
<?php
require dirname(__DIR__).'/Common.php';

$processManager = \Workerfy\processManager::getInstance();
$process_name = 'Dbtest';
$process_class = \Workerfy\Tests\Db\Worker::class;
$process_worker_num = 1;
$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;

$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);


$processManager->onStart = function ($pid) {
    //var_dump("fffff");
    // file_put_contents 不能用在协程中，否则主进程存在异步IO,子进程reboot时无法重新创建
    //file_put_contents(PID_FILE, $pid);

    // 需要运行在协程中
    go(function () use($pid) {
        sleep(5);
        $db = \Workerfy\Tests\Make::makeCommonDb();
        $res = $db->query("select * from `tbl_order` limit 1");
        var_dump($res);
    });
};

$processManager->onPipeMsg = function($msg, $from_process_name, $from_process_worker_id, $is_proxy_by_master) {
    $array = [
        $msg,
        $from_process_name,
        $from_process_worker_id,
    ];
    var_dump($array);
};


$processManager->onExit = function() use($configFilePath) {
    //var_dump("master exit",$configFilePath);
};

$processManager->onHandleException = function($t) {
    var_dump("aaaaaaaaaaaaaaaa");
    var_dump($t->getMessage());
};

$master_pid = $processManager->start();