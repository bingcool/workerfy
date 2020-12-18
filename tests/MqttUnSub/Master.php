#!/usr/bin/php
<?php
define("START_SCRIPT_ROOT", __DIR__);
define("START_SCRIPT_FILE", __FILE__);
date_default_timezone_set('Asia/Shanghai');

// 默认在当前目录runtime下
define("PID_FILE_ROOT", '/tmp/workerfy/log/MqttUnSub');
// 不存在则创建
if(!is_dir(PID_FILE_ROOT)) {
    mkdir(PID_FILE_ROOT,0777,true);
}
$pid_file = PID_FILE_ROOT.'/'.pathinfo(__FILE__)['filename'].'.pid';
define("PID_FILE", $pid_file);


$dir_config = dirname(__DIR__);
$root_path = dirname($dir_config);

include $root_path."/vendor/autoload.php";

$config_file_path = $dir_config."/Config/config.php";

$Config = \Workerfy\ConfigLoad::getInstance();
$Config->loadConfig($config_file_path);

$processManager = \Workerfy\processManager::getInstance();

$process_name = 'test-master-matt_unsub';
$process_class = \Workerfy\Tests\MqttUnSub\Worker::class;
$process_worker_num = 1;
$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;
$processManager->createCliPipe(true);
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
//        $db = \Workerfy\Tests\Db::getMasterMysql();
//        $query = $db->query("SELECT * FROM user LIMIT 1");
//        $res = $query->fetchAll(\PDO::FETCH_ASSOC);  //获取结果集中的所有数据
//        var_dump($res);
//    });
};

$processManager->onCreateDynamicProcess = function ($process_name, $process_num) use($processManager) {
    $this->createDynamicProcess($process_name, $process_num);
};


$processManager->onExit = function() use($config_file_path) {
    //var_dump("master exit",$config_file_path);
};

$master_pid = $processManager->start();
