#!/usr/bin/php
<?php
require dirname(__DIR__).'/Common.php';

$processManager = \Workerfy\processManager::getInstance();
$process_name = 'test-master-mqtt_pub';
$process_class = \Workerfy\Tests\MqttPub\Worker::class;
$process_worker_num = 1;
$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;

$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);

$processManager->onStart = function ($pid) {

    file_put_contents(PID_FILE, $pid);

    // 模拟sleep
    // 然后子进程发送消息给父进程，看是否父进程能够收到,子进程会变成僵死进程
    sleep(10);
};

// 状态上报
$processManager->onReportStatus =  function ($status) {
    //子进程会变成僵死进程,但在ReportStatus的定时器中，依然调用rebootOrExitHandle()处理，回收僵死进程，所以可以避免僵死进程的大量存在
    //var_dump($status);
    //var_dump($status);
    file_put_contents(STATUS_FILE, json_encode($status, JSON_UNESCAPED_UNICODE));

    // 需要运行在协程中
//    go(function () {
//        $db = \Workerfy\Tests\Make::makeMysql();
//        $res = $db->query("SELECT * FROM tbl_users LIMIT 1");
//        var_dump($res);
//    });
};

$processManager->onCreateDynamicProcess = function ($process_name, $process_num) use($processManager) {
    $this->createDynamicProcess($process_name, $process_num);
};


$processManager->onExit = function() use($configFilePath) {
    //var_dump("master exit",$configFilePath);
};

$master_pid = $processManager->start();
