#!/usr/bin/env php
<?php
require dirname(__DIR__).'/Common.php';

$processManager = \Workerfy\ProcessManager::getInstance();
$process_name = 'test-proxy';
$process_class = \Workerfy\Tests\Proxy\Worker::class;
$process_worker_num = 2; // 启动两个子进程，worker_id 分别为0 ，1
$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;

$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);


$processManager->onStart = function ($pid) {
    file_put_contents(PID_FILE, $pid);
};

// 父进程收到某个子进程请求，希望父进程向其他某个子进程代理转发信息
//$processManager->onProxyMsg = function($msg, $from_process_name, $from_process_worker_id, $to_process_name, $to_process_worker_id) {
//    $array = [
//        $msg,
//        $from_process_name,
//        $from_process_worker_id,
//        $to_process_name,
//        $to_process_worker_id
//    ];
//    var_dump("父进程已收到代理转发信息");
//    $this->writeByMasterProxy($msg, $from_process_name, $from_procesarticle\ArticleBatchOperater($userId);
//        $articleBatchObj->setParams($params);
//        $articleBatchObj->updateGroupIds($group_id);s_worker_id, $to_process_name, $to_process_worker_id);
//    var_dump('父进程开始转发给子进程：'.$to_process_name.'@'.$to_process_worker_id);
//};


$processManager->onExit = function() {
    var_dump("master exit");
};

$master_pid = $processManager->start();
