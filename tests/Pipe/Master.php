#!/usr/bin/php
<?php
require dirname(__DIR__).'/Common.php';

$processManager = \Workerfy\ProcessManager::getInstance();
$process_name = 'test-pipe';
$process_class = \Workerfy\Tests\Pipe\Worker::class;
$process_worker_num = 1;
$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;
$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);


$processManager->onStart = function ($pid) {
    file_put_contents(PID_FILE, $pid);
};
// 父进程收到某个子进程的信息，同时向该子进程回复信息
$processManager->onPipeMsg = function($msg, $from_process_name, $from_process_worker_id) use($processManager) {
    $array = [
        $msg,
        $from_process_name,
        $from_process_worker_id,
    ];
    if(is_array($msg))
    {
        var_dump("父进程收到信息 : ". json_encode($msg, 256));
    }else {
        var_dump("父进程收到信息 : ". $msg);
    }

    $processManager->writeByProcessName(
        $from_process_name,
        ['msg' => '子进程'.$from_process_name.'@'.$from_process_worker_id. ' 你好，我已收到你的信息']);

    var_dump("父进程开始向子进程回复信息.....");
};

$processManager->onExit = function()  {
    //var_dump("master exit",$configFilePath);
};

$processManager->onCliMsg = function(\Workerfy\Dto\PipeMsgDto $pipeMsgDto) {
    var_dump($pipeMsgDto->toArray());
};

$master_pid = $processManager->start();
