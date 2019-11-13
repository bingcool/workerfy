#!/usr/bin/php
<?php
define("START_SCRIPT_ROOT", __DIR__);
define("START_SCRIPT_FILE", __FILE__);
date_default_timezone_set('Asia/Shanghai');

//当前pid的目录
//为了方便测试存放在/tmp下，实际生产不能设置在/tmp下
//需要根据项目模块目录调整,不能直接复制这行
define("PID_FILE_ROOT", '/tmp/workerfy/log/RebortStatus');

$pid_file = PID_FILE_ROOT.'/'.pathinfo(__FILE__)['filename'].'.pid';

// 不存在则创建
if(!is_dir(PID_FILE_ROOT)) {
    mkdir(PID_FILE_ROOT,0777,true);
}

// 定义pid_file常量
define("PID_FILE", $pid_file);

// 可以定义全局变量改变上报状态时间间隔，单位秒
define("WORKERFY_REPORT_TICK_TIME", 2);

$dir_config = dirname(__DIR__);
$root_path = dirname($dir_config);

include $root_path.'/src/Ctrl.php';

include $root_path."/vendor/autoload.php";

$config_file_path = $dir_config."/Config/config.php";

$Config = \Workerfy\ConfigLoad::getInstance();
$Config->loadConfig($config_file_path);

$processManager = \Workerfy\processManager::getInstance();

$process_name = 'test-report-status';
$process_class = \Workerfy\Tests\DbSleep\Worker::class;
$process_worker_num = 2;
$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;
$processManager->createCliPipe(false);
$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);


//$processManager->writeByProcessName($process_name, 0);

$processManager->onStart = function ($pid) {
};

// 状态上报
$processManager->onReportStatus =  function ($status) {
    var_dump("master status");
    // 需要运行在协程中
    go(function () {
        $db = \Workerfy\Tests\Db::getMasterMysql();
        $query = $db->query("select sleep(5)");
        $res = $query->fetchAll(\PDO::FETCH_ASSOC);  //获取结果集中的所有数据
        var_dump($res);
    });
};


$processManager->onExit = function() use($config_file_path) {
    //var_dump("master exit",$config_file_path);
};

$master_pid = $processManager->start();
