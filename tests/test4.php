#!/usr/bin/php
<?php
define("START_SCRIPT_ROOT", __DIR__);
define("START_SCRIPT_FILE", __FILE__);
date_default_timezone_set('Asia/Shanghai');
// 默认在当前目录runtime下
define("PID_FILE_ROOT", '/tmp/workerfy/log/Curl');

$pid_file = PID_FILE_ROOT.'/'.pathinfo(__FILE__)['filename'].'.pid';
define("PID_FILE", $pid_file);

$dir_config = dirname(__DIR__);
$root_path = dirname($dir_config);

include "../vendor/autoload.php";

$config_file_path = "./Config/queue_conf.php";

\Swoole\Coroutine::create(function () {
    $client = new \GuzzleHttp\Client();
    $url = 'http://127.0.0.1:9502/index/testJson';
    $response = $client->request('GET', $url);

    //var_dump($response->getBody()->getContents());
});




