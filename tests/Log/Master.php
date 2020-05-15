#!/usr/bin/php
<?php
define("START_SCRIPT_ROOT", __DIR__);
define("START_SCRIPT_FILE", __FILE__);
date_default_timezone_set('Asia/Shanghai');

// 默认在当前目录runtime下
define("PID_FILE_ROOT", '/tmp/workerfy/log/log');
// 不存在则创建
define("PID_FILE", PID_FILE_ROOT.'/'.pathinfo(__FILE__)['filename'].'.pid');

$dir_config = dirname(__DIR__);
$root_path = dirname($dir_config);

include $root_path."/vendor/autoload.php";

$config_file_path = $dir_config."/Config/config.php";

$Config = \Workerfy\ConfigLoad::getInstance();
$Config->loadConfig($config_file_path);

// 注册log操作对象
$logManager = \Workerfy\Log\LogManager::getInstance()->registerLogger('default', __DIR__.'/'.pathinfo(__FILE__)['filename'].'.log');

$processManager = \Workerfy\processManager::getInstance();

$process_name = 'test-logger-test';
$process_class = \Workerfy\Tests\Log\Worker::class;

$process_worker_num = getenv('num') ?: 2;

$async = true;
$args = [
    'wait_time' => 1
];
$extend_data = null;
// 设置启用管道，默认不设置
$processManager->createCliPipe(true);
$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);


$processManager->onStart = function ($pid) {
    $logger = \Workerfy\Log\LogManager::getInstance()->getLogger();
    $logger->info('中国有{num}人口',['num'=>10000000000],false);
    file_put_contents(PID_FILE, $pid);

    //throw new \RuntimeException("ffffffff");

    go(function () use($pid) {
        //sleep(5);
        $db = \Workerfy\Tests\Db::getMasterMysql();
        $query = $db->query("select * from user limit 1");
        $res = $query->fetchAll(\PDO::FETCH_ASSOC);  //获取结果集中的所有数据
        var_dump($res);
    });
};

$processManager->onExit = function() use($config_file_path) {
    //var_dump("master exit",$config_file_path);
};

$master_pid = $processManager->start();
