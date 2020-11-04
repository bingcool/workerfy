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

$http = new swoole_http_server("0.0.0.0", 9509);
$http->set([
    'reactor_num' => 2,
    'worker_num' => 1,
    'max_request' => 100000,
    'task_tmpdir' => '/dev/shm',
    'reload_async' => true,
    'daemonize' => 0,
    'enable_coroutine' => 1,
    //'hook_flags' => SWOOLE_HOOK_ALL | SWOOLE_HOOK_CURL
]);

$http->on('workerStart', function ($serv, $id) {
    $client = new \GuzzleHttp\Client();
    $url = 'http://www.baidu.com';
    /**
     * when set SWOOLE_HOOK_CURL, \GuzzleHttp\Client->request() will auto echo response content on the terminal.
     */
    $res = $client->request('GET', $url);
});

$http->on('request', function ($request, Swoole\Http\Response $response) use ($http) {
    if($request->server['path_info'] == '/favicon.ico' || $request->server['request_uri'] == '/favicon.ico') {
        $response->end();
        return;
    }

});

$http->start();


class BController {
    public function __destruct()
    {
        var_dump('destruct_cid:'.\co::getCid());
    }
}



class Controller extends BController {

    public function test()
    {
        var_dump('worker_cid:'.\co::getCid());

        go(function() {
            var_dump('go_cid:'.\co::getCid());
        });

    }
}