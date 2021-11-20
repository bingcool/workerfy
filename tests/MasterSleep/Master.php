#!/usr/bin/php
<?php
require dirname(__DIR__).'/Common.php';

$processManager = \Workerfy\ProcessManager::getInstance();
$process_name = 'test-master-sleep';
$process_class = \Workerfy\Tests\MasterSleep\Worker::class;
$process_worker_num = 3;
$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;
$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);

$processManager->onStart = function ($pid) {
    // onStart中不能存在协程，否则会造成reboot时存在异步io而无法启动进程
    file_put_contents(PID_FILE, $pid);
    // 模拟sleep
    // 然后子进程发送消息给父进程，看是否父进程能够收到动态创建进程指令，如果不能，说明主进程组塞住了
    //sleep(100);

    var_dump("master sleep end");

};

// 状态上报
$processManager->onReportStatus =  function ($status) {
    //子进程会变成僵死进程,但在ReportStatus的定时器中，依然调用rebootOrExitHandle()处理，回收僵死进程，所以可以避免僵死进程的大量存在
    //var_dump($status);
    //var_dump($status);
    file_put_contents(STATUS_FILE, json_encode($status, JSON_UNESCAPED_UNICODE));

    //var_dump($status);

    go(function () {
        \Swoole\Coroutine::set([
            'enable_deadlock_check' => false
        ]);
        \Swoole\Coroutine\System::sleep(1);
        var_dump('onReportStatus');
    });

    // 需要运行在协程中
    go(function () {
        $db = \Workerfy\Tests\Make::makeMysql();
        $res = $db->query("SELECT * FROM tbl_users LIMIT 1");
        var_dump($res);
    });
};


$processManager->onExit = function() use($configFilePath) {
    //var_dump("master exit",$configFilePath);
};

$processManager->onHandleException = function (\Throwable $e)
{
    var_dump($e->getMessage());
};

$master_pid = $processManager->start();
