#!/usr/bin/php
<?php
require dirname(__DIR__).'/Common.php';

$processManager = \Workerfy\ProcessManager::getInstance();
$process_name = 'test-master-http';
$process_class = \Workerfy\Tests\MasterHttp\Worker::class;
$process_worker_num = 3;
$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;
$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);

$processManager->onStart = function ($pid) {

};

$processManager->onReportStatus = function($status) {
    // HTTP API必须在协程中使用
    go(function() {
        $cli = new \Swoole\Coroutine\Http\Client('127.0.0.1', 80);
        $cli->setHeaders([
            'Host' => "localhost",
            "User-Agent" => 'Chrome/49.0.2587.3',
            'Accept' => 'text/html,application/xhtml+xml,application/xml',
            'Accept-Encoding' => 'gzip',
        ]);
        $cli->set([ 'timeout' => 1]);
        $cli->get('/index.php');
        echo $cli->body;
        $cli->close();
    });
};

$processManager->onExit = function() {
    //var_dump("master exit",$configFilePath);
};

$master_pid = $processManager->start();
