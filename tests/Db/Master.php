#!/usr/bin/php
<?php
define("START_SCRIPT_ROOT", __DIR__);
define("START_SCRIPT_FILE", __FILE__);
date_default_timezone_set('Asia/Shanghai');

var_dump(get_cfg_var('envirment'));

// 默认在当前目录runtime下
define("PID_FILE_ROOT", '/tmp/workerfy/log/Db');
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

$process_name = 'Dbtest';
$process_class = \Workerfy\Tests\Db\Worker::class;
$process_worker_num = 1;
$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;

$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);


$processManager->onStart = function ($pid) use($config_file_path) {
    //var_dump("fffff");
    // file_put_contents 不能用在协程中，否则主进程存在异步IO,子进程reboot时无法重新创建
    //file_put_contents(PID_FILE, $pid);

    // 需要运行在协程中
    go(function () use($pid) {
        sleep(5);
        $db = \Workerfy\Tests\Db::getMasterMysql();
        $query = $db->query("select * from user limit 1");
        $res = $query->fetchAll(\PDO::FETCH_ASSOC);  //获取结果集中的所有数据
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


$processManager->onExit = function() use($config_file_path) {
    //var_dump("master exit",$config_file_path);
};

$processManager->onHandleException = function($t) {
    var_dump("aaaaaaaaaaaaaaaa");
    var_dump($t->getMessage());
};

$master_pid = $processManager->start();